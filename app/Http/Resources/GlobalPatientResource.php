<?php

namespace App\Http\Resources;

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

        return [
            'id' => $this->id,
            'identity' => $this->identity,
            'clinic_id' => $this->clinic_id,
            'clinic_name' => $this->clinic?->name,
            'country_id' => $this->country_id,
            'country_name' => $this->country->name,
            'phc_service_name' => $this->phcService?->name,
            'date_of_birth' => $this->date_of_birth,
            'enabled' => $this->enabled,
            'patient_id' => $this->patient_id,
            'gender' => $this->gender,
            'upcomingTreatmentPlan' => $upcomingTreatmentPlan,
            'ongoingTreatmentPlan' => $ongoingTreatmentPlan,
            'lastTreatmentPlan' => $lastTreatmentPlan
        ];
    }
}
