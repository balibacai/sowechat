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
        $from_name = array_get($event->from, 'RemarkName') ?: array_get($event->from, 'NickName');
        $to_name = array_get($event->to, 'RemarkName') ?: array_get($event->to, 'NickName');

        $message = new WechatMessage([
            'type' => MessageType::getType($event->type),
            'content' => is_array($event->value) ? json_encode($event->value, JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES) : $event->value,
            'from_user_name' => $event->from ? array_get($event->from, 'UserName') : null,
            'from_user_nick' => $from_name,
            'to_user_name' => $event->to ? array_get($event->to, 'UserName') : null,
            'to_user_nick' => $to_name,
            'info' => json_encode($event->info, JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES),
        ]);
        $message->save();
        Log::info('insert message to db', array_except($message->toArray(), ['info']));
    }
}
