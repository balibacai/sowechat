# Wechat(WeiXin) Web Api Based On Laravel PHP Framework
 
[中文文档：基于Laravel PHP框架的微信网页版Api](https://github.com/mistcheng/sowechat/blob/master/readme_zh.md)

[![Build Status](https://travis-ci.org/mistcheng/sowechat.svg)](https://travis-ci.org/mistcheng/sowechat)
[![Total Downloads](https://poser.pugx.org/mistcheng/sowechat/d/total.svg)](https://packagist.org/packages/mistcheng/sowechat)
[![Latest Stable Version](https://poser.pugx.org/mistcheng/sowechat/v/stable.svg)](https://packagist.org/packages/mistcheng/sowechat)
[![Latest Unstable Version](https://poser.pugx.org/mistcheng/sowechat/v/unstable.svg)](https://packagist.org/packages/mistcheng/sowechat)
[![License](https://poser.pugx.org/mistcheng/sowechat/license.svg)](https://packagist.org/packages/mistcheng/sowechat)

## Features
>1. `7*24`hours no-end running,
>2. easy to use, support sending/receiving multi type messages
>3. graceful system architecture,  support `cross platform` development and flexible custom `extensions`
>4. support `Restful Api`, `async message processing` and`message event broadcasting`
>5. based on `Php`,  the best language in The World! :)

## System Architecture
![System Architecture](http://oukei.me/images/sowechat_arch_v1.1.svg)
>1. This System is composed of 3 independent components
>2. The `Middle` component is core of The System,  doing qrcode scan & message listening; I do a lot of work for pupose of robust. Meantime, as a connector, it supports message-sending ability for the `Left` component, and pushs simple-formated message to the `Right` component. Run command `php artisan wechat:listen` to make it working.
>3. The`Left`component is used for sending message, the user can send message to any friends in his code. class `App\Console\Commands\WechatSend` is a sample, run command`php artisan wechat:send` to make it working.
>4. The`Right`component is used for processing message, the user can do anything for the coming message. The job`App\Jobs\ProcessWechatMessage` process each message into more formatted message(text,share,image,voice,file, etc). Next, It fires the `App\Events\WechatMessageEvent` event to the subscribers, who can do some custom things with the message. class `App\Listeners\SaveWechatMessageListener` is a sample that saving the message into the DB.
>5. `Advantage`: The three components are 3 independent Process. this can grantee the non-ending running of the `Middle` part, at the same time, the user can do any extensions in the `Left` and `Right` part without disturbing the `Middle` part.
 
## Prerequisite
>1. php 5.6 or more
>2. php composer
>3. redis (optional)
>4. mysql (optional)

## Usage
### 1. Installation
```bash
git clone https://github.com/mistcheng/sowechat.git
cd sowechat
composer install
```

### 2. Configration
#### 2.1 `config/wechat.php` is wechat config file
```php
<?php
return [

    'debug' => env('WECHAT_DEBUG', false), // debug mode

    'web_api' => [
        'connect_timeout' => 30, // http request timeout
        'max_attempts' => 10, // max request attempts in half minutes
    ],

    'job' => [
        'connection' => env('QUEUE_DRIVER', 'database'), // wehcat message queu engine, recommend database|redis
        'queue' => env('JOB_QUEUE', 'default'), // queue name
    ],
];

```
#### 2.2 Queue Configuration (Recommend database or redis, never use async)
>In section`2.1`, the option`wechat.job.connection` should be configured in the file `config/database.php`.

##### 2.2.1 if set `wechat.job.connection`with`database`, the option `database.connections.mysql`must be configured correctly
```php
'connections' => [
        'mysql' => [
            'driver' => 'mysql',
            'host' => env('DB_HOST', 'localhost'),
            'port' => env('DB_PORT', '3306'),
            'database' => env('DB_DATABASE', 'forge'),
            'username' => env('DB_USERNAME', 'forge'),
            'password' => env('DB_PASSWORD', ''),
            'charset' => 'utf8mb4',
            'collation' => 'utf8mb4_unicode_ci',
            'prefix' => '',
            'strict' => true,
            'engine' => null,
        ],
    ],
```

#####  2.2.2 if set `wechat.job.connection`with`redis`, the option `database.redis`should be configured properly.
```php
'redis' => [

        'cluster' => false,

        'default' => [
            'host' => env('REDIS_HOST', 'localhost'),
            'password' => env('REDIS_PASSWORD', null),
            'port' => env('REDIS_PORT', 6379),
            'database' => 0,
        ],

    ],
```

#### 2.3 Running

##### 2.3.1 First time, init DB script
```bash
cd sowechat
php artisan migrate
```

##### 2.3.2 Run the `Middle`component for new
```bash
php artisan wechat:listen --new
```
>There will be a new qrcode in the folder `storage/app/wechat`, use your wechat scaning it to login.

##### 2.3.3 Run the `Middle`component without re-login
```bash
php artisan wechat:listen
```
>Run the command without passing argument --new

##### 2.3.4 Processing wechat message
```bash
php artisan queue:work
```
>See Job`App\Jobs\ProcessWechatMessage` and sample class `App\Listeners\SaveWechatMessageListener` for more detail.

##### 2.3.5 Sending wechat message (Console)
```bash
php artisan wechat:send
```
>See class `App\Console\Commands\WechatSend` for more detail.

##### 2.3.6 Sending wechat message (Web Api)
```bash
php artisan wechat:serve --port=your_port
```
>When you call the api, you must start the web server.
>The easiest way is start a mini server with the command above.
>Also, you can deploy you code use `Apache` `Nginx` etc

**there has some examples, this will return a json response `{'ret':0, 'message':'xxx'}`, success if the `ret` equals 0**
```bash
# send text, POST request, need params `to` and `content`
curl -H 'Accept:application/json' --data "to=$to_user_name&content=$your_content" http://localhost:$your_port/api/wechat/messages/text
```
```bash
# send image, POST request, need params `to` and `path`
curl -H 'Accept:application/json' --data "to=$to_user_name&path=$image_path" http://localhost:$your_port/api/wechat/messages/image
```
```bash
# send emotion, POST request, need params `to` and `path`
curl -H 'Accept:application/json' --data "to=$to_user_name&path=$emotion_path" http://localhost:$your_port/api/wechat/messages/emotion
```
```bash
# send file, POST request, need params `to` and `path`
curl -H 'Accept:application/json' --data "to=$to_user_name&path=$file_path" http://localhost:$your_port/api/wechat/messages/file
```

## OpenSource

>[MIT](http://opensource.org/licenses/MIT)

## Statement

>**This software shall not be used for commercial purposes only**