<?php

namespace App\Events;

use App\Models\Exercise;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class ApplyExerciseAutoTranslationEvent
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    /**
     * @var \App\Models\Exercise
     */
    public $exercise;

    /**
     * @var string
     */
    public $langCode;

    /**
     * @param \App\Models\Exercise $exercise
     * @param string $langCode
     *
     * @return void
     */
    public function __construct(Exercise $exercise, $langCode = null)
    {
        $this->exercise = $exercise;
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
