<?php

namespace App\Http\Resources;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class EntitiesByClinicResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'rehab_service_admin_count' => $this->users->where('type', User::ADMIN_GROUP_CLINIC_ADMIN)->count(),
            'therapist_count' => $this->therapist_count,
            'patient_count' => $this->patient_count,
        ];
    }
}
