<?php

namespace App\Console\Commands;

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
     * @var \App\Extensions\Wechat\WebApi
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
        dd($this->api->getUUID());
    }
}
