<?php

namespace App\Events;

use App\Models\EducationMaterial;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class ApplyMaterialAutoTranslationEvent
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    /**
     * @var \App\Models\EducationMaterial
     */
    public $educationMaterial;

    /**
     * @var string
     */
    public $langCode;

    /**
     * @param \App\Models\EducationMaterial $educationMaterial
     * @param string $langCode
     *
     * @return void
     */
    public function __construct(EducationMaterial $educationMaterial, $langCode = null)
    {
        $this->educationMaterial = $educationMaterial;
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
