<?php

namespace App\Http\Resources\Internal;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class MfaSettingResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            ...parent::toArray($request),
            'mfa_expiration_duration_in_seconds' => $this->mfa_expiration_duration_in_seconds,
            'skip_mfa_setup_duration_in_seconds' => $this->skip_mfa_setup_duration_in_seconds,
        ];
    }
}
