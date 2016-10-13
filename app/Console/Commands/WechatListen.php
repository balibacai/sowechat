<?php

namespace App\Console\Commands;

use Cache;
use Illuminate\Console\Command;
use App\Extensions\Wechat\WebApi;

class WechatListen extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'wechat:listen
                            {--new : start a new login}';

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
        parent::__construct();
    }

    /**
     * Execute the console command.
     *
     * @return mixed
     */
    public function handle()
    {
        if ($this->option('new')) {
            WebApi::clearState();
            $this->api = new WebApi();
        } else {
            $this->api = WebApi::restoreState();
        }
        $this->api->run();
    }
}
