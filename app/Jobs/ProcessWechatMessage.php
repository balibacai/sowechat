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

        // pre process & format
        switch ($this->type) {
            case MessageType::Text:

                $this->value = $this->info['Content'];

                // It's a Location
                if (! empty($this->info['Url'])) {
                    $this->type = MessageType::Location;
                    $this->value = [
                        'address' => array_first(explode(':', $this->value)),
                        'url' => $this->info['Url'],
                    ];
                }
                break;

            case MessageType::LinkShare:

                if (array_get($this->from, 'Type') == 'public') {
                    $this->type = MessageType::PublicLinkShare;
                }

                $xml = str_replace('<br/>', '', htmlspecialchars_decode($this->info['Content']));
                $xml = simplexml_load_string($xml, 'SimpleXMLElement', LIBXML_NOCDATA);
                $data = json_decode(json_encode($xml), true);
                $items = array_get($data, 'appmsg.mmreader.category.item');
                if (! empty($items) && is_array(array_first($items))) {
                    $news = array_map(function ($item) {
                        return array_only($item, ['title', 'digest', 'url']);
                    }, $items);
                } else {
                    $news = json_encode(array_only($data['appmsg'], ['title', 'des', 'url']), JSON_UNESCAPED_UNICODE);
                }
                $this->value = $news;
                break;

            case MessageType::Card:
                $this->value = array_only(array_get($this->info, 'RecommendInfo', []), ['NickName', 'Province', 'City']);
                break;

            case MessageType::System:
                $this->value = array_get($this->info, 'Content');
                break;
        }

        // fire event to consumers
        Event::fire(new WechatMessageEvent($this->type, $this->from, $this->to, $this->value, $this->info));
    }
}
