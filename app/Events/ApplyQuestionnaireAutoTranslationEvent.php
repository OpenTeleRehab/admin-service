<?php

namespace App\Events;

use App\Models\Questionnaire;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class ApplyQuestionnaireAutoTranslationEvent
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    /**
     * @var \App\Models\Questionnaire
     */
    public $questionnaire;

    /**
     * @var string
     */
    public $langCode;

    /**
     * @param \App\Models\Questionnaire $questionnaire
     * @param string $langCode
     *
     * @return void
     */
    public function __construct(Questionnaire $questionnaire, $langCode = null)
    {
        $this->questionnaire = $questionnaire;
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
