<?php

namespace App\Events;

use App\Models\EmailTemplate;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class ApplyEmailTemplateAutoTranslationEvent
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    /**
     * @var \App\Models\EmailTemplate
     */
    public $emailTemplate;

    /**
     * @var string
     */
    public $langCode;

    /**
     * @param \App\Models\EmailTemplate $email_template
     * @param string $langCode
     *
     * @return void
     */
    public function __construct(EmailTemplate $email_template, $langCode = null)
    {
        $this->emailTemplate = $email_template;
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
