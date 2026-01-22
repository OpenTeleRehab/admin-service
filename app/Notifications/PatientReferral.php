<?php

namespace App\Notifications;

use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class PatientReferral extends Notification
{
    // use Queueable;

    /**
     * Create a new notification instance.
     */
    public function __construct()
    {
        $this->subject = 'OpenTeleRehab – Patient Referral Notification';
    }

    /**
     * Get the notification's delivery channels.
     *
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    /**
     * Get the mail representation of the notification.
     */
    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject($this->subject)
            ->greeting("Dear $notifiable->first_name,")
            ->line('Please be informed that a rehab service admin, [Rehab Service Admin Name], has assigned a patient referral request to you. Kindly log in to the portal and take the appropriate action.');
    }
}
