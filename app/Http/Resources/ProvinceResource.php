<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ProvinceResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'identity' => str_pad($this->id, 4, '0', STR_PAD_LEFT),
            'region_id' => $this->region_id,
            'region_name' => $this->region->name,
            'name' => $this->name,
            'therapist_limit' => $this->therapist_limit,
            'phc_worker_limit' => $this->phc_worker_limit,
        ];
    }
}
