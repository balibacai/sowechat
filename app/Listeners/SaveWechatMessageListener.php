<?php

namespace App\Listeners;

use Log;
use App\WechatMessage;
use App\Events\WechatMessageEvent;
use App\Extensions\Wechat\MessageType;
use Illuminate\Contracts\Queue\ShouldQueue;

/**
 * save message to db
 *
 * Class SaveWechatMessageListener
 * @package App\Listeners
 */
class SaveWechatMessageListener
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
        $message = new WechatMessage([
            'type' => MessageType::getType($event->type),
            'content' => $event->value,
            'from_user_name' => $event->from ? array_get($event->from, 'UserName') : null,
            'from_user_nick' => $event->from ? array_get($event->from, 'NickName') : null,
            'to_user_name' => $event->to ? array_get($event->to, 'UserName') : null,
            'to_user_nick' => $event->to ? array_get($event->to, 'NickName') : null,
            'info' => json_encode($event->info, JSON_UNESCAPED_UNICODE),
        ]);
        $message->save();
        Log::info('insert message to db', array_except($message->toArray(), ['info']));
    }
}
