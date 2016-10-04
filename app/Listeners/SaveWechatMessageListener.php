<?php

namespace App\Listeners;

use App\Events\WechatMessageEvent;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Contracts\Queue\ShouldQueue;

class WechatMessageListener
{
    /**
     * Create the event listener.
     *
     * @return void
     */
    public function __construct()
    {
        //
    }

    /**
     * Handle the event.
     *
     * @param  WechatMessageEvent  $event
     * @return void
     */
    public function handle(WechatMessageEvent $event)
    {
        //
    }
}
