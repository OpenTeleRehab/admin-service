<?php

namespace App\Events;

use App\Models\Translation;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class ApplyTranslationAutoTranslationEvent
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    /**
     * @var \App\Models\TermAndCondition
     */
    public $translation;

    /**
     * @var string
     */
    public $langCode;

    /**
     * @param Translation $translation
     * @param string $langCode
     *
     * @return void
     */
    public function __construct(Translation $translation, $langCode = null)
    {
        $this->translation = $translation;
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
