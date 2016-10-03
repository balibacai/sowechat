<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Extensions\Wechat\WebApi;

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
     * @return void
     */
    public function __construct()
    {
        $this->api = WebApi::restoreState();
        parent::__construct();
    }

    /**
     * Execute the console command.
     *
     * @return mixed
     */
    public function handle()
    {
        $this->api->run();
    }
}
