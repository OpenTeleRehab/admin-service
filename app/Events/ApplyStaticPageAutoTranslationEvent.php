<?php

namespace App\Events;

use App\Models\StaticPage;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class ApplyStaticPageAutoTranslationEvent
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    /**
     * @var \App\Models\StaticPage
     */
    public $staticPage;

    /**
     * @var string
     */
    public $langCode;

    /**
     * @param \App\Models\StaticPage $staticPage
     * @param string $langCode
     *
     * @return void
     */
    public function __construct(StaticPage $staticPage, $langCode = null)
    {
        $this->staticPage = $staticPage;
        $this->langCode = $langCode;
    }

    /**
     * Get the channels the event should broadcast on.
     *
     * @return \Illuminate\Broadcasting\Channel|array
     */
    public function broadcastOn()
    {
        return new PrivateChannel('channel-name');
    }
}
