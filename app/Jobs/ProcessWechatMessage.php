<?php

namespace App\Jobs;

use App\Extensions\Wechat\MessageType;
use Event;
use Illuminate\Bus\Queueable;
use App\Events\WechatMessageEvent;
use Illuminate\Queue\SerializesModels;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Contracts\Queue\ShouldQueue;

class ProcessWechatMessage implements ShouldQueue
{
    use InteractsWithQueue, Queueable, SerializesModels;

    protected $type;
    protected $from = [];
    protected $to = [];
    protected $value;
    protected $info = [];

    /**
     * Create a new job instance.
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
     * Execute the job.
     *
     * @return void
     */
    public function handle()
    {
        if ($this->type == MessageType::Init) {
            return null;
        }

        // TODO pre process & format
        switch ($this->type) {
            case MessageType::Text:
                break;

            case MessageType::LinkShare:
                break;
        }

        // fire event to consumers
         Event::fire(new WechatMessageEvent($this->type, $this->from, $this->to, $this->value, $this->info));
    }
}
