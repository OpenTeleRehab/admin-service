<?php

namespace App\Events;

use App\Models\Guidance;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class ApplyGuidanceAutoTranslationEvent
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    /**
     * @var \App\Models\Guidance
     */
    public $guidance;

    /**
     * @var string
     */
    public $langCode;

    /**
     * @param \App\Models\Guidance $guidance
     * @param string $langCode
     *
     * @return void
     */
    public function __construct(Guidance $guidance, $langCode = null)
    {
        $this->guidance = $guidance;
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
