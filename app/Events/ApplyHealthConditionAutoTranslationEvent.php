<?php

namespace App\Events;

use App\Models\HealthCondition;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class ApplyHealthConditionAutoTranslationEvent
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    /**
     * @var HealthCondition
     */
    public $healthCondition;

    /**
     * @var string
     */
    public $langCode;

    /**
     * @param HealthCondition $healthCondition
     * @param string $langCode
     *
     * @return void
     */
    public function __construct(HealthCondition $healthCondition, $langCode = null)
    {
        $this->healthCondition = $healthCondition;
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
