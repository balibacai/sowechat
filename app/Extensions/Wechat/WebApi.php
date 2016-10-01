<?php

namespace App\Extensions\Wechat;

use Log;
use Storage;
use Exception;
use GuzzleHttp\Client;

class WebApi
{

    /**
     * @var \GuzzleHttp\Client
     */
    protected $client;
    /**
     * @var array
     */
    protected $loginInfo = []; // ['skey', 'wxsid', 'wxuin', 'pass_ticket']
    /**
     * @var SyncKey
     */
    protected $syncKey;

    /**
     * login user
     * @var array
     */
    protected $user = [];

    /**
     * 用户通讯录
     * @var Contact
     */
    protected $contact = [];

    public function __construct(SyncKey $syncKey)
    {
        // important, don't allow auto redirect
        $this->client = new Client(['cookies' => new CookieJar(), 'allow_redirects' => false]);
        $this->syncKey = $syncKey;
    }

    public function run()
    {
        while (true) {
            $uuid = $this->getUUID();
            $qrcode_login_url = $this->getQRCode($uuid);

            Storage::put('wechat/qrcode.png', file_get_contents($qrcode_login_url));

            while (true) {
                if ($login_info = $this->loginListen($uuid)) {
                    $this->loginInit();
                    $this->statusNotify();
                    $this->getContact();
                    $this->getBatchGroupMembers();

                    while (true) {
                        $check_info = $this->syncCheck();
                    }
                }
            }
        }
    }

    protected function request($method, $uri, array $options = [])
    {
        $default = [
            'headers' => [
                'User-Agent' => 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_10_5) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/53.0.2785.116 Safari/537.36',
            ],
        ];

        $options = array_replace_recursive($default, $options);

        Log::info('request headers', $options);

        $response = $this->client->request($method, $uri, $options);

        if (! in_array($response->getStatusCode(), ['200', '301', '302'])) {
            throw new Exception('Request Error');
        }

        return $response->getBody()->getContents();
    }

    /**
     * 生成当前时间戳（毫秒）
     * @return int
     */
    protected function getTimeStamp()
    {
        return intval(microtime(true) * 1000);
    }

    /**
     * 当前时间取反 (获取getTimeStamp低32位数据,然后去反操作)
     * @return int
     */
    protected function getReverseTimeStamp()
    {
        $timestamp = $this->getTimeStamp();
        return 0xFFFFFFFF + (($timestamp >> 32 << 32) - $timestamp);
    }

    /**
     * 获取设备id
     * @return string
     */
    protected function getDeviceId()
    {
        return 'e' . random_int(100000000000000, 999999999999999);
    }

    protected function getBaseRequest()
    {
        return [
            'DeviceID' => $this->getDeviceId(),
            'Sid' => $this->loginInfo['wxsid'],
            'Skey' => $this->loginInfo['skey'],
            'Uin' => $this->loginInfo['wxuin'],
        ];
    }

    /**
     * 返回uuid
     * @return string uuid
     * @throws Exception
     */
    public function getUUID()
    {
        $url = 'https://login.weixin.qq.com/jslogin';
        $response = $this->request('GET', $url, [
            'query' => [
                'appid' => 'wx782c26e4c19acffb',
                'fun' => 'new',
                'lang' => 'zh_CN',
                '_' => $this->getTimeStamp(),
            ]
        ]);

        preg_match('|window.QRLogin.code = (\d+); window.QRLogin.uuid = "(\S+?)";|', $response, $matches);

        if (empty($matches) || count($matches) != 3 || intval($matches[1]) != 200) {
            throw new Exception('get uuid parse error');
        }

        return $matches[2];
    }

    public function getQRCode($uuid)
    {
        return 'https://login.weixin.qq.com/qrcode/' . $uuid;
    }

    /**
     * 监听用户扫码登录
     * @param $uuid
     * @return null|string 成功会返回redirect_uri，否则返回null
     * @throws Exception
     */
    public function loginListen($uuid)
    {
        Log::info('listening user scan qrcode to login');
        $url = 'https://login.wx2.qq.com/cgi-bin/mmwebwx-bin/login';
        $response = $this->request('GET', $url, [
            'query' => [
                'uuid' => $uuid,
                'tip' => 0,
                '_' => $this->getTimeStamp(),
            ]
        ]);

        Log::info('response ' . $response);

        preg_match('|window.code=(\d+);|', $response, $matches);

        if (empty($matches) || count($matches) != 2) {
            return null;
        }

        $code = intval($matches[1]);

        if ($code != 200) {
            return null;
        }

        preg_match('|window.redirect_uri="(\S+?)";|', $response, $matches);
        if (empty($matches) || count($matches) != 2) {
            throw new Exception('login success parse error');
        }

        return $this->loginConfirm($matches[1]);
    }

    public function loginConfirm($redirect_uri)
    {
        $response = $this->request('GET', $redirect_uri);

        $info = simplexml_load_string($response);
        if ($info && ($info = (array)$info) && $info['ret'] == 0) {
            return array_only($info, ['skey', 'wxsid', 'wxuin', 'pass_ticket']);
        }

        return null;
    }

    public function loginInit()
    {
        $url = 'https://wx2.qq.com/cgi-bin/mmwebwx-bin/webwxinit';

        $response = $this->request('POST', $url, [
            'headers' => [
                'Content-Type' => 'application/json; charset=UTF-8',
            ],
            'query' => [
                'r' => $this->getReverseTimeStamp(),
                'pass_ticket' => $this->loginInfo['pass_ticket'],
            ],
            'body' => json_encode([
                'BaseRequest' => $this->getBaseRequest(),
            ])
        ]);

        $content = json_decode($response, true);

        if (! $content && array_get($content, 'BaseResponse.Ret') !== 0) {
            throw new Exception('webwxinit fail');
        }

        $this->syncKey->refresh(array_get($content, 'SyncKey', []));

        $this->user = array_get($content, 'User', []);

        return $content;
    }

    public function statusNotify()
    {
        $url = 'https://wx2.qq.com/cgi-bin/mmwebwx-bin/webwxstatusnotify';
        $response = $this->request('POST', $url, [
            'headers' => [
                'Content-Type' => 'application/json; charset=UTF-8',
            ],
            'body' => json_encode(
                [
                    'BaseRequest' => $this->getBaseRequest(),
                    'ClientMsgId' => $this->getTimeStamp(),
                    'Code' => 3,
                    'FromUserName' => array_get($this->user, 'UserName'),
                    'ToUserName' => array_get($this->user, 'UserName'),
                ]
            )
        ]);

        $content = json_decode($response, true);

        if (! $content && array_get($content, 'BaseResponse.Ret') !== 0) {
            throw new Exception('statusnotify fail');
        }

        return $content;
    }

    /**
     * syncCheck
     * @return mixed {retcode:"xxx", selector:"xxx"}
     * @throws Exception
     */
    public function syncCheck()
    {
        $url = 'https://webpush.wx2.qq.com/cgi-bin/mmwebwx-bin/synccheck';
        $response = $this->request('GET', $url, [
            'query' => [
                '_' => $this->getTimeStamp(),
                'r' => $this->getTimeStamp(),
                'skey' => $this->loginInfo['skey'],
                'sid' => $this->loginInfo['wxsid'],
                'uin' => $this->loginInfo['wxuin'],
                'deviceid' => $this->getDeviceId(),
                'synckey' => $this->syncKey->toString(),
            ]
        ]);

        preg_match('|window.synccheck="(\S+?)";|', $response, $matches);
        if (empty($matches) || count($matches) != 2) {
            throw new Exception('synccheck response parse error');
        }

        return json_decode($matches[1], true);
    }

    public function syncDetail()
    {
        $url = 'https://wx2.qq.com/cgi-bin/mmwebwx-bin/webwxsync?sid=&skey=@&lang=en_US';

        $response = $this->request('POST', $url, [
            'headers' => [
                'Content-Type' => 'application/json; charset=UTF-8',
            ],
           'query' => [
               'skey' => $this->loginInfo['skey'],
               'sid' => $this->loginInfo['wxsid'],
               'lang' => 'zh_CN',
           ],
           'body' => json_encode(
               [
                   'BaseRequest' => $this->getBaseRequest(),
                   'SyncKey' => $this->syncKey->getData(),
                   'rr' => ~$this->getReverseTimeStamp(),
               ]
           )
        ]);

        $content = json_decode($response, true);

        if (array_get($content, 'BaseResponse.Ret') !== '0') {
            throw new Exception('webwxsync error');
        }

        $this->syncKey->refresh(array_get($content, 'SyncKey'));

        return $content;
    }

    public function getContact()
    {
        $url = 'https://wx2.qq.com/cgi-bin/mmwebwx-bin/webwxgetcontact?lang=en_US&r=1475322309689&seq=0&skey=@crypt_4597c5ec_d604537018e16998ac4e3dfab300fdde';
        $response = $this->request('GET', $url, [
            'query' => [
                'lang' => 'zh_CN',
                'r' => $this->getTimeStamp(),
                'seq' => 0,
                'skey' => $this->loginInfo['skey'],
            ],
        ]);

        $content = json_decode($response, true);

        if (array_get($content, 'BaseResponse.Ret') !== '0') {
            throw new Exception('getcontact error');
        }

        $this->contact = new Contact(array_get($content, 'MemberList', []));
    }

    public function getBatchGroupMembers()
    {
        $url = 'https://wx2.qq.com/cgi-bin/mmwebwx-bin/webwxbatchgetcontact';

        foreach(array_chunk($this->contact->getGroups(), 30) as $groups) {
            $response = $this->request('POST', $url, [
                'query' => [
                    'lang' => 'zh_CN',
                    'r' => $this->getTimeStamp(),
                    'type' => 'ex',
                ],
                'body' => json_encode(
                    [
                        'BaseRequest' => $this->getBaseRequest(),
                        'Count' => count($groups),
                        'List' => array_map(function($group) {
                            return array_only($group, ['UserName', 'EncryChatRoomId']);
                        }, $groups),
                    ]
                )
            ]);

            $content = json_decode($response, true);

            if (array_get($content, 'BaseResponse.Ret') !== '0') {
                throw new Exception('getcontact error');
            }

            foreach(array_get($content, 'ContactList', []) as $group_list) {
                $this->contact->setGroupMembers($group_list['UserName'], $group_list['MemberList']);
            }
        }
    }
}
