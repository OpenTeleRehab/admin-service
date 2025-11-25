<?php

namespace App\Http\Resources;

use App\Models\Clinic;
use App\Models\Country;
use App\Models\Organization;
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
            'clinics' => $this->clinic_ids ? Clinic::whereIn('id', $this->clinic_ids)->pluck('name') : [],
            'countries' => $this->country_ids ? Country::whereIn('id', $this->country_ids)->pluck('name') : [],
            'organizations' => $this->organizations,
            'organizations_name' => $this->organizations ? Organization::whereIn('id', $this->organizations)->pluck('name') : [],
            'mfa_enforcement' => $this->mfa_enforcement,
            'mfa_expiration_duration' => $this->mfa_expiration_duration,
            'skip_mfa_setup_duration' => $this->skip_mfa_setup_duration,
            'job_status' => $this->jobTrackers
                ->sortByDesc('created_at')
                ->first(),
        ];
    }
}
