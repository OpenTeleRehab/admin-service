<?php

namespace App\Events;

use App\Models\PrivacyPolicy;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class ApplyPrivacyPolicyAutoTranslationEvent
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    /**
     * @var \App\Models\PrivacyPolicy
     */
    public $privacyPolicy;

    /**
     * @var string
     */
    public $langCode;

    /**
     * @param \App\Models\PrivacyPolicy $privacyPolicy
     * @param string $langCode
     *
     * @return void
     */
    public function __construct(PrivacyPolicy $privacyPolicy, $langCode = null)
    {
        $this->privacyPolicy = $privacyPolicy;
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
