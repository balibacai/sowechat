<?php

namespace App\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Queue\SerializesModels;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;

class WechatMessageEvent
{
    use InteractsWithSockets, SerializesModels;

    public $type;
    public $from = [];
    public $to = [];
    public $value;
    public $info = [];

    /**
     * WechatMessageEvent constructor.
     * @param int $type
     * @param array $from
     * @param array $to
     * @param string $value
     * @param array $info
     */
    public function __construct($type, $from, $to, $value, $info = [])
    {
        $this->type = $type;
        $this->from = $from;
        $this->to = $to;
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
