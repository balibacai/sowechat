<?php

namespace App\Console\Commands;

use Log;
use Illuminate\Console\Command;
use App\Extensions\Wechat\WebApi;

class WechatSend extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'wechat:send';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'test wechat message send';

    /**
     * @var WebApi
     */
    protected $api;

    /**
     * Create a new command instance.
     *
     * @return void
     */
    public function __construct()
    {
        parent::__construct();
    }

    /**
     * Execute the console command.
     *
     * @return mixed
     */
    public function handle()
    {
        $this->api = WebApi::restoreState();
        $user = $this->api->getLoginUser();
        $from = $user['UserName'];
        $to = $from;
        Log::info('send text message');
        $this->api->sendMessage($to, 'hello 世界 http://www.baidu.com');
        Log::info('send image');
        $this->api->sendImage($to, storage_path('app/wechat/qrcode.png'));
        Log::info('send emotion');
        $this->api->sendEmotion($to, storage_path('app/wechat/image/test.gif'));
        Log::info('send file');
        $this->api->sendFile($to, storage_path('app/wechat/core_state.txt'));
    }
}
