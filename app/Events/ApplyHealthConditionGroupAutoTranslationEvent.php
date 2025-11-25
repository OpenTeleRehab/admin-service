<?php

namespace App\Events;

use App\Models\HealthConditionGroup;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class ApplyHealthConditionGroupAutoTranslationEvent
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    /**
     * @var HealthConditionGroup
     */
    public $healthConditionGroup;

    /**
     * @var string
     */
    public $langCode;

    /**
     * @param HealthConditionGroup $healthConditionGroup
     * @param string $langCode
     *
     * @return void
     */
    public function __construct(HealthConditionGroup $healthConditionGroup, $langCode = null)
    {
        $this->healthConditionGroup = $healthConditionGroup;
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
