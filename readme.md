# 基于Laravel PHP框架的微信网页版Api

[![Build Status](https://travis-ci.org/mistcheng/sowechat.svg)](https://travis-ci.org/mistcheng/sowechat)
[![Total Downloads](https://poser.pugx.org/mistcheng/sowechat/d/total.svg)](https://packagist.org/packages/mistcheng/sowechat)
[![Latest Stable Version](https://poser.pugx.org/mistcheng/sowechat/v/stable.svg)](https://packagist.org/packages/mistcheng/sowechat)
[![Latest Unstable Version](https://poser.pugx.org/mistcheng/sowechat/v/unstable.svg)](https://packagist.org/packages/mistcheng/sowechat)
[![License](https://poser.pugx.org/mistcheng/sowechat/license.svg)](https://packagist.org/packages/mistcheng/sowechat)

## 特性
>1. `7*24`小时无终止运行, 弥补其他类似开源项目不能稳定运行的缺陷
>2. 功能完善, 简单易用, 支持消息的发送/接收
>3. 良好的架构设计, 支持`跨平台`的消息发送 & 支持灵活`可扩展`的消息处理能力
>4. 支持`Restful Api`接口调用, 支持`消息异步处理`, 支持消息`事件广播`
>5. 采用`Php`开发, `Php`是世界上最好的语言

## 系统架构图
![系统架构图](http://oukei.me/images/sowechat_arch_v1.0.svg)
>1. 该系统有三个部分组成, 各部分相互独立、互不影响
>2. `中间部分`为整个系统的核心部分, 负责微信扫码登录以及监听消息等, 为了系统`7*24`小时无终止运行, 该系统做了大量鲁棒性的工作。同时，该部分起到桥梁作用，对上游（左边部分）提供发送消息的能力；对下游（右边部分）推送经过封装的监听到的最新消息；该部分通过调用`php artisan wechat:listen`命令运行
>3. `左边部分`为系统发送消息的部分，用户可以自定义发送逻辑。同时该部分又是跨平台的。样例参考 `App\Console\Commands\WechatSend`， 在控制台中运行`php artisan wechat:send`即可看到效果
>4. `右边部分`为系统处理消息的部分，用户可以自定义处理逻辑。通过job`App\Jobs\ProcessWechatMessage` 的处理，已经将消息进行详细的分类（文本、分享、图片、语音、文件等），并将重要信息提取出来。该部分系统采用`事件`的机制，经格式化的消息作为事件`App\Events\WechatMessageEvent`发送出去，任何订阅者都可对消息事件进行个性化的处理，样例参考`App\Listeners\SaveWechatMessageListener`，它做的工作就是把消息存储到数据库中。
>5. `优点`：左中右三部分之间是三个独立的进程，相互之间互不影响。这样既能保证中间部分的稳定运行，用户又可以自定义扩展自己的发送和接收逻辑。
 

## 依赖软件
>1. php 5.6以上版本
>2. php composer
>3. redis(可选)
>4. mysql(可选) 

## 使用
### 1. 安装
```bash
git clone https://github.com/mistcheng/sowechat.git
cd sowechat
composer install
```

### 2. 配置
#### 2.1 config/wechat.php文件为微信配置文件
```php
<?php
return [

    'debug' => env('WECHAT_DEBUG', false), // debug模式

    'web_api' => [
        'connect_timeout' => 30, // http请求超时时间，防止假死
        'max_attempts' => 10, // 半分钟内频繁请求次数，防止微信接口失效导致的大量请求
    ],

    'job' => [
        'connection' => env('QUEUE_DRIVER', 'database'), // 微信消息入队列引擎，推荐database|redis
        'queue' => env('JOB_QUEUE', 'default'), // 队列名称
    ],
];

```
#### 2.2 队列配置 (推荐使用database 或者 redis, 不推荐async)
>`2.1`中`wechat.job.connection`选项需要在`config/database.php`中配置, 推荐使用database模式, 比较直观

##### 2.2.1 如果connection选择`database`，则需配置选项`database.connections.mysql`
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

#####  2.2.2 如果connection选择`redis`，则需配置`database.redis`
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

#### 2.3 运行

##### 2.3.1 执行数据库脚本迁移指令
```bash
cd sowechat
php artisan migrate
```

##### 2.3.2 首次运行微信
```bash
php artisan wechat:listen --new
```
>在storage/app/wechat目录下会生成一张二维码，扫码登录

##### 2.3.3 再次运行微信
```bash
php artisan wechat:listen
```
>命令不加--new参数时，程序会使用上一次的登录态来运行，这样可避免再次扫码

##### 2.3.4 微信消息处理
```bash
php artisan queue:work
```
>微信消息最终会有`App\Jobs\ProcessWechatMessage`Job来处理，在Job在对消息进行解析分类后会触发`App\Events\WechatMessageEvent`事件，事件订阅者可以进行后续处理，例如订阅者`App\Listeners\SaveWechatMessageListener`会将事件中的消息保存到数据库中。

##### 2.3.5 微信消息发送
```bash
php artisan wechat:send
```
>目前仅支持命令行的发送，后续会增加api接口供调用

## 开源协议

>[MIT协议](http://opensource.org/licenses/MIT)

## 声明

>**本软件不得用于商业用途, 仅做学习交流**