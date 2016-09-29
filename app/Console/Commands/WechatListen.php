<?php

namespace App\Console\Commands;

use Cache;
use Storage;
use App\Extensions\Wechat\WebApi;
use Illuminate\Console\Command;

class WechatListen extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'wechat:listen';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'simulate web wechat and listening message';

    /**
     * @var WebApi
     */
    protected $api;

    /**
     * Create a new command instance.
     *
     * @param WebApi $api
     * @return void
     */
    public function __construct(WebApi $api)
    {
        $this->api = $api;
        parent::__construct();
    }

    /**
     * Execute the console command.
     *
     * @return mixed
     */
    public function handle()
    {
        while (true) {
            $uuid = $this->getUUID();
            $qrcode_login_url = $this->api->getQRCode($uuid);

            Storage::put('wechat/qrcode.png', file_get_contents($qrcode_login_url));

            while (true) {
                if ($login_info = $this->api->loginListen($uuid)) {
                    $this->api->loginInit($login_info);
                }
            }
        }
    }

    protected function getUUID()
    {
        $key = 'wechat_login_uuid';
        $uuid = Cache::get($key);

        if (empty($uuid)) {
            $uuid = $this->api->getUUID();
            Cache::add($key, $uuid, 3);
        }

        return $uuid;
    }
}
