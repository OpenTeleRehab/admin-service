<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;
use App\Models\Clinic;
use App\Models\User;
use App\Models\Region;
use App\Models\PhcService;
use App\Models\Country;
use App\Models\Province;
use App\Models\ExtendActivity;
use Carbon\Carbon;

class AuditLogResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return array
     */
    public function toArray($request)
    {
        $fullName = '';
        $userGroup = '';
        $userClinic = '';
        $userCountry = '';
        $userRegion = '';
        $userPhcService = '';
        $userProvince = '';
        $changes = $this->changes;
        if ($this->country_id || $this->region_id || $this->province_id || $this->clinic_id || $this->phc_service_id ) {
            $fullName = $this->causer_name ?? ExtendActivity::UNKNOWN;
            $userGroup = $this->causer_group ?? ExtendActivity::UNKNOWN;
            if ($this->country_id) {
                $country = Country::find($this->country_id);
                $userCountry = $country?->name ?? ExtendActivity::UNKNOWN;
            }

            if ($this->region_id) {
                $region = Region::find($this->region_id);
                $userRegion = $region?->name ?? ExtendActivity::UNKNOWN;
            }

            if ($this->province_id) {
                $province = Province::find($this->province_id);
                $userProvince = $province?->name ?? ExtendActivity::UNKNOWN;
            }

            if ($this->clinic_id) {
                $clinic = Clinic::find($this->clinic_id);
                $userClinic = $clinic?->name ?? ExtendActivity::UNKNOWN;
            }

            if ($this->phc_service_id) {
                $phcService = PhcService::find($this->phc_service_id);
                $userPhcService = $phcService?->name ?? ExtendActivity::UNKNOWN;
            }
        } else {
            $user = $this->user;
            $fullName = $user ? $user->last_name . ' ' . $user->first_name : ExtendActivity::UNKNOWN;
            $userGroup = $user?->type ?? ExtendActivity::UNKNOWN;
            $userClinic = $user?->clinic_id ? $user?->clinic?->name ?? ExtendActivity::UNKNOWN : '';
            $userCountry = $user?->country_id ? $user?->country?->name ?? ExtendActivity::UNKNOWN : '';
            $userPhcService = $user?->phc_service_id ? $user?->phcService?->name ?? ExtendActivity::UNKNOWN : '';

            if ($user?->region_id) {
                $userRegion = $user?->region?->name ?? ExtendActivity::UNKNOWN;
            } elseif ($user?->type === User::ADMIN_GROUP_REGIONAL_ADMIN && $user?->regions) {
                $userRegion = $user?->regions->pluck('name')->join(', ') ?: ExtendActivity::UNKNOWN;
            }

            if ($user?->clinic_id) {
                $userProvince = $user?->clinic?->province ? $user?->clinic?->province?->name ?? ExtendActivity::UNKNOWN : '';
            } elseif ($user?->phc_service_id) {
                $userProvince = $user?->phcService?->province ? $user?->phcService?->province?->name ?? ExtendActivity::UNKNOWN : '';
            }
        }

        $beforeChanged = isset($changes['old']) ? $changes['old'] : [];
        $afterChanged = isset($changes['attributes']) ? $changes['attributes'] : [];
        $subjectType = last(explode('\\', $this->subject_type));
        unset($beforeChanged['auto_translated']);
        unset($afterChanged['auto_translated']);
        if (isset($beforeChanged['created_at'])) {
            $beforeChanged['created_at'] = Carbon::parse($beforeChanged['created_at'])->format('Y-m-d');
        }
        if (isset($beforeChanged['updated_at'])) {
            $beforeChanged['updated_at'] = Carbon::parse($beforeChanged['updated_at'])->format('Y-m-d');
        }
        if (isset($afterChanged['created_at'])) {
            $afterChanged['created_at'] = Carbon::parse($afterChanged['created_at'])->format('Y-m-d');
        }
        if (isset($afterChanged['updated_at'])) {
            $afterChanged['updated_at'] = Carbon::parse($afterChanged['updated_at'])->format('Y-m-d');
        }

        return [
            'id' => $this->id,
            'resource' => $this->log_name,
            'type_of_changes' => $this->description,
            'who' => $fullName,
            'country' => $userCountry,
            'region' => $userRegion,
            'province' => $userProvince,
            'clinic' => $userClinic,
            'phc_service' => $userPhcService,
            'user_group' => $userGroup,
            'date_time' => $this->created_at,
            'subject_type' => $subjectType,
            'before_changed' => $beforeChanged,
            'after_changed' => $afterChanged
        ];
    }
}
