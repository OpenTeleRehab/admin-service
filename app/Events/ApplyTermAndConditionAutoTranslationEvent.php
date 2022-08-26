<?php

namespace App\Events;

use App\Models\TermAndCondition;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class ApplyTermAndConditionAutoTranslationEvent
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    /**
     * @var \App\Models\TermAndCondition
     */
    public $termAndCondition;

    /**
     * @var string
     */
    public $langCode;

    /**
     * @param \App\Models\TermAndCondition $termAndCondition
     * @param string $langCode
     *
     * @return void
     */
    public function __construct(TermAndCondition $termAndCondition, $langCode = null)
    {
        $this->termAndCondition = $termAndCondition;
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
