<?php

namespace App\Events;

use App\Models\AssistiveTechnology;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class ApplyAssistiveTechnologyAutoTranslationEvent
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    /**
     * @var \App\Models\AssistiveTechnology
     */
    public $assistiveTechnology;

    /**
     * @var string
     */
    public $langCode;

    /**
     * @param \App\Models\AssistiveTechnology $assistive_technology
     * @param string $langCode
     *
     * @return void
     */
    public function __construct(AssistiveTechnology $assistive_technology, $langCode = null)
    {
        $this->assistiveTechnology = $assistive_technology;
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
