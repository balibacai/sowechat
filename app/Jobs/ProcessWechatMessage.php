<?php

namespace App\Jobs;

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
    protected $sender;
    protected $value;
    protected $info = [];

    /**
     * Create a new job instance.
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
     * Execute the job.
     *
     * @return void
     */
    public function handle()
    {
        // TODO pre process & format

        // fire event to consumers
        Event::fire(new WechatMessageEvent($this->type, $this->sender, $this->value, $this->info));
    }
}
