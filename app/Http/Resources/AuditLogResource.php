<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;
use App\Helpers\KeycloakHelper;
use App\Models\User;

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
        $logName = $this->log_name;
        $fullName = '';
        $userGroups = [];
        $changes = [];
        if ($logName === 'admin_service') {
            $changes = $this->changes;
            $user = User::find($this->causer_id);
            $userGroups = KeycloakHelper::getUserGroup();
            $fullName = $user->full_name;
        } else {
            $changes = $this->getExtraProperty('customProperty');
            if (isset($changes['meta'])) {
                $fullName = $changes['meta']['user_full_name'];
                $userGroups = $changes['meta']['user_groups'];
            }
        }

        $beforeChanged = isset($changes['old']) ? $changes['old'] : [];
        $afterChanged = isset($changes['attributes']) ? $changes['attributes'] : [];
        return [
            'id' => $this->id,
            'resource' => $logName,
            'type_of_changes' => $this->description,
            'who' => $fullName,
            'user_groups' => $userGroups,
            'date_time' => $this->created_at,
            'before_changed' => $beforeChanged,
            'after_changed' => $afterChanged
        ];
    }
}
