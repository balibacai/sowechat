<?php

namespace App\Extensions\Wechat;

use Log;
use Exception;
use GuzzleHttp\Client;

class WebApi
{

    /**
     * @var \GuzzleHttp\Client
     */
    protected $client;

    public function __construct()
    {
        // important, don't allow auto redirect
        $this->client = new Client(['cookies' => new CookieJar(), 'allow_redirects' => false]);
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

    public function loginInit($login_info)
    {
        $url = sprintf('https://wx2.qq.com/cgi-bin/mmwebwx-bin/webwxinit?r=%d&pass_ticket=%s',
            $this->getTimeStamp(), $login_info['pass_ticket']);

        $response = $this->request('POST', $url, [
            'headers' => [
                'Content-Type' => 'application/json; charset=UTF-8',
            ],
            'debug' => true,
            'body' => json_encode([
                'BaseRequest' => [
                    'DeviceID' => 'e' . random_int(100000000000000, 999999999999999),
                    'Sid' => $login_info['wxsid'],
                    'Skey' => $login_info['skey'],
                    'Uin' => $login_info['wxuin'],
                ]
            ])
        ]);

        $content = json_decode($response, true);

        if (! $content && array_get($content, 'BaseResponse.Ret', 1100) !== 0) {
            throw new Exception('webwxinit fail');
        }

        return $content;
    }
}
