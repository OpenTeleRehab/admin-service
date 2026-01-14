<?php

namespace App\Http\Resources;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class EntitiesByPhcServiceResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'phc_service_admin_count' => $this->users->where('type', User::ADMIN_GROUP_PHC_SERVICE_ADMIN)->count(),
            'phc_worker_count' => $this->phc_worker_count,
            'patient_count' => $this->patient_count,
        ];
    }
}
