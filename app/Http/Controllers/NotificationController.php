<?php

namespace App\Http\Controllers;

use App\Models\Forwarder;
use App\Notifications\PatientCounterReferral;
use App\Notifications\PatientReferralAssignment;
use Illuminate\Http\Request;
use App\Notifications\PatientReferral;
use App\Models\User;
use Illuminate\Support\Facades\Http;

class NotificationController extends Controller
{
    public function patientReferral(Request $request)
    {
        $request->validate([
            'clinic_id' => 'required|exists:clinics,id',
            'phc_worker_id' => 'required|integer',
        ]);

        $clinicId = $request->integer('clinic_id');

        // Fetch healthcare worker information.
        $healthcareWorker = Http::withToken(Forwarder::getAccessToken(Forwarder::THERAPIST_SERVICE))->get(env('THERAPIST_SERVICE_URL') . '/therapist/by-id', [
            'id' => $request->integer('phc_worker_id'),
        ]);

        if ($healthcareWorker->successful()) {
            $healthcareWorker = $healthcareWorker->json();
            $name = $healthcareWorker['last_name'] . ' ' . $healthcareWorker['first_name'];

            User::where('clinic_id', $clinicId)
                ->where('notifiable', 1)
                ->get()
                ->map(function (User $user) use ($name) {
                    $user->notify(new PatientReferral($user, $name));
                });
        }
    }

    public function patientReferralAssignment(Request $request)
    {
        $request->validate([
            'clinic_id' => 'required|exists:clinics,id',
            'therapist_id' => 'required|integer',
            'status' => 'required|string|in:accepted,declined',
        ]);

        $clinicId = $request->integer('clinic_id');
        $therapistId = $request->integer('therapist_id');
        $status = $request->get('status');

        $accessToken = Forwarder::getAccessToken(Forwarder::THERAPIST_SERVICE);

        $therapist = Http::withToken($accessToken)->get(env('THERAPIST_SERVICE_URL') . '/therapist/by-id', [
            'id' => $therapistId,
        ]);

        if ($therapist->successful()) {
            User::where('clinic_id', $clinicId)
                ->where('notifiable', 1)
                ->get()
                ->map(function (User $user) use ($therapist, $status) {
                    $user->notify(new PatientReferralAssignment($user, $therapist, $status));
                });
        }
    }

    public function patientCounterReferral(Request $request)
    {
        $request->validate([
            'clinic_id' => 'required|exists:clinics,id',
            'therapist_id' => 'required|integer',
        ]);

        $clinicId = $request->integer('clinic_id');
        $therapistId = $request->integer('therapist_id');

        $accessToken = Forwarder::getAccessToken(Forwarder::THERAPIST_SERVICE);

        $therapist = Http::withToken($accessToken)->get(env('THERAPIST_SERVICE_URL') . '/therapist/by-id', [
            'id' => $therapistId,
        ]);

        if ($therapist->successful()) {
            User::where('clinic_id', $clinicId)
                ->where('notifiable', 1)
                ->get()
                ->map(function (User $user) use ($therapist) {
                    $user->notify(new PatientCounterReferral($user, $therapist));
                });
        }
    }
}
