<?php

namespace App\Http\Controllers;

use App\Notifications\PatientCounterReferral;
use App\Notifications\PatientReferralAssignment;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Notification;
use App\Notifications\PatientReferral;
use App\Models\User;

class NotificationController extends Controller
{
    public function patientReferral(Request $request)
    {
        $request->validate([
            'clinic_id' => 'required|exists:clinics,id',
        ]);

        $clinicId = $request->integer('clinic_id');

        $users = User::where('clinic_id', $clinicId)
            ->where('notifiable', 1)
            ->get();

        Notification::send($users, new PatientReferral());
    }

    public function patientReferralAssignment(Request $request)
    {
        $request->validate([
            'clinic_id' => 'required|exists:clinics,id',
            'status' => 'required|string|in:accepted,declined'
        ]);

        $clinicId = $request->integer('clinic_id');
        $status = $request->get('status');

        $users = User::where('clinic_id', $clinicId)
            ->where('notifiable', 1)
            ->get();

        Notification::send($users, new PatientReferralAssignment($status));
    }

    public function patientCounterReferral(Request $request)
    {
        $request->validate([
            'clinic_id' => 'required|exists:clinics,id',
        ]);

        $clinicId = $request->integer('clinic_id');

        $users = User::where('clinic_id', $clinicId)
            ->where('notifiable', 1)
            ->get();

        Notification::send($users, new PatientCounterReferral());
    }
}
