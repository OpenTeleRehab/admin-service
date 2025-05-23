<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class ClinicResource extends JsonResource
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
            'identity' => str_pad($this->id, 4, '0', STR_PAD_LEFT),
            'name' => $this->name,
            'country_id' => $this->country_id,
            'region' => $this->region,
            'province' => $this->province,
            'city' => $this->city,
            'therapist_limit' => $this->therapist_limit,
            'phone' => $this->phone,
            'dial_code' => $this->dial_code,
        ];
    }
}
