<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class PhcServiceResource extends JsonResource
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
            'region_id' => $this->province?->region_id,
            'region_name' => $this->province?->region?->name,
            'province_id' => $this->province_id,
            'province_name' => $this->province?->name,
            'phc_worker_limit' => $this->phc_worker_limit,
            'phone_number' => $this->phone_number,
            'dial_code' => $this->dial_code
        ];
    }
}
