<?php

namespace App\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Queue\SerializesModels;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Broadcasting\PresenceChannel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;

class WechatMessageEvent
{
    use InteractsWithSockets, SerializesModels;

    protected $type;
    protected $sender;
    protected $value;
    protected $info = [];

    /**
     * WechatMessageEvent constructor.
     * @param $type
     * @param $sender
     * @param $value
     * @param array $info
     */
    public function __construct($type, $sender, $value, $info = [])
    {
        $this->type = $type;
        $this->sender = $sender;
        $this->value = $value;
        $this->info = $info;
    }

    /**
     * Get the channels the event should broadcast on.
     *
     * @return Channel|array
     */
    public function broadcastOn()
    {
        return new PrivateChannel('channel-name');
    }
}
