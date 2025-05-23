<?php

namespace App\Http\Resources;

use App\Models\User;
use Carbon\Carbon;
use Illuminate\Http\Resources\Json\JsonResource;

class GlobalPatientResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return array
     */
    public function toArray($request)
    {
        $today = Carbon::today();
        $upcomingTreatmentPlan = $this->treatmentPlans()
            ->whereDate('start_date', '>', $today)
            ->orderBy('start_date')
            ->first();

        $ongoingTreatmentPlan = $this->treatmentPlans()
            ->whereDate('start_date', '<=', $today)
            ->whereDate('end_date', '>=', $today)
            ->orderBy('start_date')
            ->get();

        $lastTreatmentPlan = $this->treatmentPlans()
            ->orderBy('end_date', 'desc')
            ->first();

        $responseData = [
            'id' => $this->id,
            'identity' => $this->identity,
            'clinic_id' => $this->clinic_id,
            'country_id' => $this->country_id,
            'date_of_birth' => $this->date_of_birth,
            'enabled' => $this->enabled,
            'patient_id' => $this->patient_id,
            'gender' => $this->gender,
            'upcomingTreatmentPlan' => $upcomingTreatmentPlan,
            'ongoingTreatmentPlan' => $ongoingTreatmentPlan,
            'lastTreatmentPlan' => $lastTreatmentPlan
        ];

        if ($request->get('type') !== User::ADMIN_GROUP_ORG_ADMIN) {
            $responseData = array_merge($responseData, [
                'first_name' => $this->first_name,
                'last_name' => $this->last_name,
                'phone' => $this->phone,
                'dial_code' => $this->dial_code,
                'gender' => $this->gender,
                'chat_user_id' => $this->chat_user_id,
                'chat_rooms' => $this->chat_rooms ?: [],
                'therapist_id' => $this->therapist_id,
                'secondary_therapists' => $this->secondary_therapists ? : [],
                'note' => $this->note,
                'is_secondary_therapist' => $this->isSecondaryTherapist($this->secondary_therapists, $request),
                'completed_percent' => $this->completed_percent,
                'total_pain_threshold' => $this->total_pain_threshold,
            ]);
        }

        return $responseData;
    }

    /**
     * @param $secondary_therapists
     * @param $request
     * @return bool
     */
    private function isSecondaryTherapist($secondary_therapists, $request)
    {
        $isSecondaryTherapist = false;
        if (!empty($secondary_therapists) && in_array($request->get('therapist_id'), $secondary_therapists)) {
            $isSecondaryTherapist = true;
        }

        return $isSecondaryTherapist;
    }
}
