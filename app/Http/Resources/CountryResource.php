<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class CountryResource extends JsonResource
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
            'iso_code' => strtoupper($this->iso_code),
            'phone_code' => $this->phone_code,
            'language_id' => $this->language_id,
            'therapist_limit' => $this->therapist_limit,
        ];
    }
}
