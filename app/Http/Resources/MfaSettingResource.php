<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class MfaSettingResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     */
    public function toArray($request): array
    {
        return [
            'id' => $this->id,
            'role' => $this->role,
            'country_ids' => $this->country_ids,
            'clinic_ids' => $this->clinic_ids,
            'organizations' => $this->organizations,
            'attributes' => $this->attributes,
            'job_status' => $this->jobTrackers
                ->sortByDesc('created_at')
                ->first(),
        ];
    }
}
