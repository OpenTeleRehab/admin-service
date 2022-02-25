<?php

namespace App\Helpers;

use Illuminate\Support\Facades\Mail;

/**
 * @package App\Helpers
 */
class OrganizationHelper
{
    /**
     * @param string $email
     * @param string $org_name
     * @param string $subject
     * @param string $message
     *
     * @return \Illuminate\Http\RedirectResponse
     */
    public static function sendEmailNotification($email, $org_name, $status)
    {
        $data = [
            'subject' => $org_name . ' Organization Creation is ' . $status,
            'email' => $email,
            'org_name' => $org_name,
            'status' => $status,
        ];

        Mail::send('organizations.mail', $data, function ($message) use ($data) {
            $message->to($data['email'])->subject($data['subject']);
        });

        return back()->with(['message' => 'Email successfully sent!']);
    }
}
