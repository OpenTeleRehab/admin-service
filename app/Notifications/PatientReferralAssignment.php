<?php

namespace App\Notifications;

use App\Models\EmailTemplate;
use App\Models\Language;
use App\Models\User;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class PatientReferralAssignment extends Notification
{
    // use Queueable;

    private string $subject;
    private string $content;

    /**
     * Create a new notification instance.
     */
    public function __construct(User $user, object $therapist, string $status)
    {
        $prefix = '';

        // Define prefix based on status.
        switch ($status) {
            case 'accepted':
                $prefix = 'therapist-accepts-the-assigned-patient-referral-request-for-rehab-service-admin';
                break;
            case 'declined':
                $prefix = 'therapist-declines-the-assigned-patient-referral-request-for-rehab-service-admin';
                break;
        }

        // Find user current language.
        $language = Language::find($user->language_id);

        // Find email template by prefix.
        $emailTemplate = EmailTemplate::where('prefix', $prefix)->firstOrFail();

        $this->subject = config('mail.from.name') . ' - ' . $emailTemplate->getTranslation('title', $language->code);
        $this->content = $emailTemplate->getTranslation('content', $language->code);

        // Replace email content.
        $this->content = str_replace('#user_name#', $user->last_name . ' ' . $user->first_name, $this->content);
        $this->content = str_replace('#therapist_name#', $therapist['last_name'] . ' ' . $therapist['first_name'], $this->content);
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
