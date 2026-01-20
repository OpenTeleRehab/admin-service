<?php

namespace App\Http\Resources;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class EntitiesByRegionResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'regional_admin_count' => $this->users->where('type', User::ADMIN_GROUP_REGIONAL_ADMIN)->count(),
            'province_count' => $this->provinces->count(),
            'rehab_service_count' => $this->clinics->count(),
            'rehab_service_admin_count' => $this->users->where('type', User::ADMIN_GROUP_CLINIC_ADMIN)->count(),
            'phc_service_count' => $this->phcServices->count(),
            'phc_service_admin_count' => $this->users->where('type', User::ADMIN_GROUP_PHC_SERVICE_ADMIN)->count(),
            'therapist_count' => $this->therapist_count,
            'phc_worker_count' => $this->phc_worker_count,
            'patient_count' => $this->patient_count,
        ];
    }
}
