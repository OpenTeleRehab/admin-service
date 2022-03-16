<?php

namespace App\Helpers;

use App\Models\Organization;
use App\Models\User;
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
        $organization = Organization::where('admin_email', $email)->first();
        $user = User::find($organization->created_by);

        $payloads = [
            [
                'subject' => $org_name . ' Organization Creation is ' . $status,
                'email' => $user->email,
                'org_name' => $org_name,
                'status' => $status,
                'internal' => true,
            ],
            [
                'subject' => $org_name . ' Organization Creation is ' . $status,
                'email' => 'devops@web-essentials.co',
                'org_name' => $org_name,
                'status' => $status,
                'internal' => true,
            ],
            [
                'subject' => $org_name . ' Organization Creation is ' . $status,
                'email' => $email,
                'org_name' => $org_name,
                'status' => $status,
                'internal' => false,
            ]
        ];

        foreach ($payloads as $payload) {
            Mail::send('organizations.mail', $payload, function ($message) use ($payload) {
                $message->to($payload['email'])->subject($payload['subject']);
            });
        }

        return back()->with(['message' => 'Email successfully sent!']);
    }
}
