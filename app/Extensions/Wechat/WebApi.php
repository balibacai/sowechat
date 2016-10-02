<?php

namespace App\Extensions\Wechat;

use Log;
use Event;
use Cache;
use Storage;
use Exception;
use GuzzleHttp\Client;
use Illuminate\Cache\RateLimiter;
use App\Events\WechatMessageEvent;
use Psr\Http\Message\ResponseInterface;

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
     * user contact
     * @var Contact
     */
    protected $contact;

    /**
     * request limit
     * @var RateLimiter
     */
    protected $limiter;

    /**
     * max request attempts in 0.5 min
     * @var int
     */
    protected $maxAttempts = 10;

    public function __construct($options = [])
    {
        // important, don't allow auto redirect
        $this->client = new Client([
            'cookies' => new CookieJar(),
            'allow_redirects' => false,
            'http_errors' => false,
            'debug' => array_get($options, 'debug', false),
        ]);

        $this->limiter = app(RateLimiter::class);
        $this->limiter->clear('synccheck');
        $this->limiter->clear('wechat_login');
    }

    public function run()
    {
        while (true) {
            try {
                // wait until user login
                do {
                    if ($this->tooManyAttempts('wechat_login')) {
                        Cache::forget('wechat_login_uuid');
                        sleep(5);
                        break;
                    }

                    // regenerate uuid when time exceed 5 min
                    if (($uuid = Cache::get('wechat_login_uuid')) == null) {
                        $uuid = $this->getUUID();
                        Storage::put('wechat/qrcode.png', file_get_contents($this->getQRCode($uuid)));
                        Cache::put('wechat_login_uuid', $uuid, 5);
                    }

                } while (! $this->loginListen($uuid));

                while ($this->reload());

            } catch (Exception $e) {
                Log::error($e->getMessage(), array_slice($e->getTrace(), 0, 5));
            }
        }
    }

    /**
     * reload the whole page (first init when login or when too many requests at one time)
     * @return bool $need_reload
     * @throws Exception
     */
    protected function reload()
    {
        $need_reload = false;

        $this->loginInit();
        $this->statusNotify();

        // get contact
        try {
            $this->getContact();
            $this->getBatchGroupMembers();
        } catch (Exception $e) {
            Log::error('get contact error', array_slice($e->getTrace(), 0, 5));
        }

        // message listen
        while (true) {

            if ($this->tooManyAttempts('synccheck')) {
                sleep(5);
                $need_reload = true;
                break;
            }

            $check_status = $this->syncCheck();

            switch ($check_status) {
                case SyncCheckStatus::NewMessage:
                    Log::info('new message');
                    try {
                        $detail = $this->syncDetail();
                        if ($detail['AddMsgCount'] > 0) {
                            $this->receiveMessage($detail['AddMsgList']);
                        }
                        if ($detail['DelContactCount'] > 0) {
                            Log::info('contact delete', $detail['DelContactList']);
                        }
                        if ($detail['ModContactCount'] > 0) {
                            Log::info('contact changed', $detail['ModContactList']);
                        }
                    } catch (Exception $e) {
                        Log::error('get contact error', array_slice($e->getTrace(), 0, 5));
                    }
                    break;

                case SyncCheckStatus::Normal:
                    Log::info('no message');
                    break;

                case SyncCheckStatus::Fail:
                    throw new Exception('lost user, please relogin');
                    break;
            }
        }

        return $need_reload;
    }

    /**
     * check if has too many request in 0.5 min
     * @param $key
     * @return bool
     */
    protected function tooManyAttempts($key)
    {
        if ($this->limiter->hit($key) && $this->limiter->tooManyAttempts($key, $this->maxAttempts, 0.5)) {
            Log::warning('too many request in 0.5 min, sleep some seconds and reload page');
            $this->limiter->clear($key);
            return true;
        }

        return false;
    }

    /**
     * base request
     * @param $method
     * @param string $uri request url
     * @param array $options request options
     * @param int $retry retry_times
     * @return string plain/text
     * @throws Exception
     */
    protected function request($method, $uri, array $options = [], $retry = 3)
    {
        $default = [
            'headers' => [
                'User-Agent' => 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_10_5) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/53.0.2785.116 Safari/537.36',
            ],
        ];

        $options = array_replace_recursive($default, $options);

        // enable retry
        while ($retry--) {
            $response = $this->client->request($method, $uri, $options);

            if ($response->getStatusCode() < 200 || $response->getStatusCode() >= 400) {
                if ($retry > 0) {
                    continue;
                }
                Log::error('request error after tries',  $response->getBody()->getMetadata());
                throw new Exception('request error after tries');
            } else {
                return $response->getBody()->getContents();
            }
        }
    }

    /**
     * get current timestamp with milliseconds
     * @return int
     */
    protected function getTimeStamp()
    {
        return intval(microtime(true) * 1000);
    }

    /**
     * simulate js ~new Date
     * 当前时间取反 (获取getTimeStamp低32位数据,然后去反操作)
     * @return int
     */
    protected function getReverseTimeStamp()
    {
        $timestamp = $this->getTimeStamp();
        return 0xFFFFFFFF + (($timestamp >> 32 << 32) - $timestamp);
    }

    /**
     * random device id
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
     * return login uuid
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

        $uuid = $matches[2];
        Log::info('get new login uuid ' . $uuid);

        return $uuid;
    }

    /**
     * get login qrcode link
     * @param $uuid
     * @return string
     */
    public function getQRCode($uuid)
    {
        Log::info('get qrcode link');
        return 'https://login.weixin.qq.com/qrcode/' . $uuid;
    }

    /**
     * listening user to login
     * @param $uuid
     * @return boolean is_success
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

        preg_match('|window.code=(\d+);|', $response, $matches);

        if (empty($matches) || count($matches) != 2) {
            return false;
        }

        $code = intval($matches[1]);

        if ($code != 200) {
            return false;
        }

        preg_match('|window.redirect_uri="(\S+?)";|', $response, $matches);
        if (empty($matches) || count($matches) != 2) {
            throw new Exception('login success parse error');
        }

        return $this->loginConfirm($matches[1]);
    }

    public function loginConfirm($redirect_uri)
    {
        Log::info('login confirm when user confirm login');
        $response = $this->request('GET', $redirect_uri);

        $info = simplexml_load_string($response);
        if ($info && ($info = (array)$info) && $info['ret'] == 0) {
            $this->loginInfo = array_only($info, ['skey', 'wxsid', 'wxuin', 'pass_ticket']);
            Log::info('user login success', $this->loginInfo);
            return true;
        }
        return false;
    }

    public function loginInit()
    {
        Log::info('login init');
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

        $this->syncKey = new SyncKey(array_get($content, 'SyncKey', []));

        $this->user = array_get($content, 'User', []);

        Log::info('success get user info', $this->user);

        return $content;
    }

    public function statusNotify()
    {
        Log::info('status notify');
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
     * @return int check status
     * @throws Exception
     */
    public function syncCheck()
    {
        Log::info('sync check');
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
        preg_match('|window.synccheck={retcode:"(\d+)",selector:"(\d+)"}|', $response, $matches);
        if (empty($matches) || count($matches) != 3) {
            throw new Exception('synccheck parse error');
        }

        $retcode = intval($matches[1]);
        $selector = intval($matches[2]);

        if ($retcode !== 0) {
            return SyncCheckStatus::Fail;
        }

        if ($selector == 0) {
            return SyncCheckStatus::Normal;
        } else if ($selector == 2) {
            return SyncCheckStatus::NewMessage;
        } else if ($selector == 7) {
            return SyncCheckStatus::NewJoin;
        } else {
            Log::warning('unrecognized synccheck selector', compact('retcode', 'selector'));
            return SyncCheckStatus::Unknown;
        }
    }

    /**
     * get detail when the method syncCheck got new message
     * @return mixed
     * @throws Exception
     */
    public function syncDetail()
    {
        Log::info('get detail when the method syncCheck got new message');
        $url = 'https://wx2.qq.com/cgi-bin/mmwebwx-bin/webwxsync';

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
                   'rr' => $this->getReverseTimeStamp(),
               ]
           )
        ]);

        $content = json_decode($response, true);

        if (array_get($content, 'BaseResponse.Ret') !== 0) {
            throw new Exception('webwxsync error');
        }

        $this->syncKey->refresh(array_get($content, 'SyncKey'));

        return $content;
    }

    /**
     * get user all contact
     * @throws Exception
     */
    public function getContact()
    {
        Log::info('get contact');
        $url = 'https://wx2.qq.com/cgi-bin/mmwebwx-bin/webwxgetcontact';
        $response = $this->request('GET', $url, [
            'query' => [
                'lang' => 'zh_CN',
                'r' => $this->getTimeStamp(),
                'seq' => 0,
                'skey' => $this->loginInfo['skey'],
            ],
        ]);

        $content = json_decode($response, true);

        if (array_get($content, 'BaseResponse.Ret') !== 0) {
            throw new Exception('getcontact error');
        }

        $this->contact = new Contact(array_get($content, 'MemberList', []));
    }

    /**
     * get group members by chunk
     * @throws Exception
     */
    public function getBatchGroupMembers()
    {
        Log::info('get group members');
        $chunk_size = 30;
        $url = 'https://wx2.qq.com/cgi-bin/mmwebwx-bin/webwxbatchgetcontact';

        foreach(array_chunk($this->contact->getGroups(), $chunk_size) as $groups) {
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

            if (array_get($content, 'BaseResponse.Ret') !== 0) {
                throw new Exception('getcontact error');
            }

            foreach(array_get($content, 'ContactList', []) as $group_list) {
                $this->contact->setGroupMembers($group_list['UserName'], $group_list['MemberList']);
            }
        }
    }

    /**
     * receive message and fire event
     * @param $messages
     */
    public function receiveMessage($messages)
    {
        foreach ($messages as $message) {
            $value = '';
            switch ($message['MsgType']) {
                case MessageType::Text:
                    $value = $message['Content'];
                    break;

                case MessageType::LinkShare:
                    $xml = str_replace('<br/>', '', htmlspecialchars_decode($message['Content']));
                    $xml = simplexml_load_string($xml, 'SimpleXMLElement', LIBXML_NOCDATA);
                    $data = json_decode(json_encode($xml), true);
                    $value = json_encode(array_only($data['appmsg'], ['title', 'des', 'url']), JSON_UNESCAPED_UNICODE);
                    break;

                case MessageType::Image:
                case MessageType::Voice:
                case MessageType::Video:
                    $value = $this->downloadMedia($message);
                    break;

                case MessageType::Init:
                    $this->loginInit();
                    break;

                default:
                    Log::info('other message type', $message);
                    break;
            }

            Log::info('success get new message', [
                'from' => $message['NickName'],
                'type' => MessageType::getType($message['MsgType']),
                'value' => $value,
            ]);

            // fire event
            Event::fire(new WechatMessageEvent($message['MsgType'], $message['FromUserName'], $value, $message));
        }
    }

    /**
     * download media message, eg, image,voice,video
     * @param $message
     * @return bool|string
     * @throws Exception
     */
    public function downloadMedia($message)
    {
        Log::info('downloadMedia', $message);
        switch ($message['MsgType']) {
            case MessageType::Image:
                $url = 'https://wx2.qq.com/cgi-bin/mmwebwx-bin/webwxgetmsgimg';
                $suffix = 'jpg';
                $path = 'wechat/image/' . $message['MsgId'] . '.' . $suffix;
                break;

            case MessageType::Voice:
                $url = 'https://wx2.qq.com/cgi-bin/mmwebwx-bin/webwxgetvoice';
                $suffix = 'mp3';
                $path = 'wechat/voice/' . $message['MsgId'] . '.' . $suffix;
                break;

            default:
                return false;
                break;
        }

        $data = $this->request('GET', $url, [
            'query' => [
                'msgid' => $message['MsgId'],
                'skey' => $this->loginInfo['skey'],
            ],
            'on_headers' => function (ResponseInterface $response) use (& $suffix) {
                $suffix = last(explode('/', $response->getHeaderLine('Content-Type')));
            }
        ]);

        Storage::put($path, $data);

        return $path;
    }
}
