<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;
use App\Models\Clinic;
use App\Models\User;
use App\Models\Country;
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
        $changes = $this->changes;
        if ($this->full_name || $this->group || $this->clinic_id || $this->country_id) {
            $fullName = $this->full_name;
            $userGroup = $this->group;
            $clinic = Clinic::find($this->clinic_id);
            $country = Country::find($this->country_id);
            $userClinic = $clinic?->name;
            $userCountry = $country?->name;
        } else {
            $user = User::find($this->causer_id);
            $fullName = $user ? $user->last_name . ' ' . $user->first_name : null;
            $userGroup = $user?->type;
            $userClinic = $user?->clinic?->name;
            $userCountry = $user?->country?->name;
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
            'clinic' => $userClinic,
            'user_group' => $userGroup,
            'date_time' => $this->created_at,
            'subject_type' => $subjectType,
            'before_changed' => $beforeChanged,
            'after_changed' => $afterChanged
        ];
    }
}
