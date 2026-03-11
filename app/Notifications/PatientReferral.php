<?php

namespace App\Notifications;

use App\Helpers\UserHelper;
use App\Models\EmailTemplate;
use App\Models\Language;
use App\Models\User;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class PatientReferral extends Notification
{
    // use Queueable;

    private string $subject;
    private string $content;

    /**
     * Create a new notification instance.
     */
    public function __construct(User $user, object $therapist)
    {
        // Find user current language.
        $language = Language::find($user->language_id);

        // Find email template by prefix.
        $emailTemplate = EmailTemplate::where('prefix', 'new-patient-referral-request-from-a-healthcare-worker')->firstOrFail();

        if ($language) {
            $this->subject = $emailTemplate->getTranslation('title', $language->code);
            $this->content = $emailTemplate->getTranslation('content', $language->code);
        } else {
            $this->subject = $emailTemplate->title;
            $this->content = $emailTemplate->content;
        }

        // Replace email content.
        $this->content = str_replace('#user_name#', UserHelper::getFullName($user->last_name, $user->first_name, $user->language_id), $this->content);
        $this->content = str_replace('#healthcare_worker_name#', UserHelper::getFullName($therapist['last_name'], $therapist['first_name'], $user->language_id), $this->content);
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
            ->view('emails.patient-referral', [
                'content' => $this->content,
            ]);
    }
}
