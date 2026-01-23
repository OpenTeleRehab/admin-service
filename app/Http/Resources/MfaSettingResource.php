<?php

namespace App\Http\Resources;

use App\Models\Clinic;
use App\Models\Country;
use App\Models\Organization;
use App\Models\PhcService;
use App\Models\Region;
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
            'region_ids' => $this->region_ids,
            'clinic_ids' => $this->clinic_ids,
            'phc_service_ids' => $this->phc_service_ids,
            'clinics' => $this->clinics,
            'countries' => $this->countries,
            'regions' => $this->regions,
            'phc_services' => $this->phc_services,
            'organizations' => $this->organizations,
            'organizations_name' => $this->organizations_name,
            'mfa_enforcement' => $this->mfa_enforcement,
            'mfa_expiration_duration' => $this->mfa_expiration_duration,
            'skip_mfa_setup_duration' => $this->skip_mfa_setup_duration,
            'mfa_expiration_unit' => $this->mfa_expiration_unit,
            'skip_mfa_setup_unit' => $this->skip_mfa_setup_unit,
            'job_status' => $this->jobTrackers
                ->sortByDesc('created_at')
                ->first(),
        ];
    }
}
