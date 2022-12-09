<?php

namespace App\Http\Resources;
use Illuminate\Http\Resources\Json\JsonResource;

class GlobalAssistiveTechnologyPatientResource extends JsonResource
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
            'identity' => $this->identity,
            'date_of_birth' => $this->date_of_birth,
            'patient_id' => $this->patient_id,
            'gender' => $this->gender,
            'assistive_technology_id' => $this->assistive_technology_id,
            'provision_date' => $this->provision_date,
        ];
    }
}
