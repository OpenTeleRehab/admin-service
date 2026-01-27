<?php

namespace App\Http\Resources;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class EntitiesByProvinceResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'rehab_service_count' => $this->clinics->count(),
            'rehab_service_admin_count' => $this->clinics->sum(fn($clinic) => $clinic->users->count()),
            'phc_service_count' => $this->phcServices->count(),
            'phc_service_admin_count' => $this->phcServices->sum(fn($phcService) => $phcService->users->count()),
            'therapist_count' => $this->therapist_count,
            'phc_worker_count' => $this->phc_worker_count,
            'patient_count' => $this->patient_count,
        ];
    }
}
