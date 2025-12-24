<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class OrganizationResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return array
     */
    public function toArray($request)
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'type' => $this->type,
            'admin_email' => $this->admin_email,
            'sub_domain_name' => $this->sub_domain_name,
            'max_number_of_therapist' => $this->max_number_of_therapist,
            'max_number_of_phc_worker' => $this->max_number_of_phc_worker,
            'max_ongoing_treatment_plan' => $this->max_ongoing_treatment_plan,
            'max_phc_ongoing_treatment_plan' => $this->max_phc_ongoing_treatment_plan,
            'max_sms_per_week' => $this->max_sms_per_week,
            'status' => $this->status,
            'max_phc_sms_per_week' => $this->max_phc_sms_per_week,
        ];
    }
}
