<?php

namespace App\Services;

use App\Helpers\KeycloakHelper;
use App\Helpers\MfaSettingHelper;
use App\Models\Forwarder;
use App\Models\MfaSetting;
use App\Models\Region;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class MfaSettingService
{
    // Recalculate MFA settings for all relevant settings below the current user's role in the hierarchy
    public function recalculate($authUserType, MfaSetting $mfaSetting, bool $isDeleting)
    {
        $organization = MfaSettingHelper::getOrganization();

        $excludeRoles = [];

        foreach (User::ROLE_HIERARCHY as $role) {
            if ($role === $authUserType) break;

            $excludeRoles[] = $role;
        }

        $excludeRoles[] = $authUserType;

        $query = MfaSetting::where('role', $mfaSetting->role);

        if ($isDeleting) {
            $query->where('id', '!=', $mfaSetting->id);
        }

        if (!empty($excludeRoles)) {
            $query->whereNotIn('created_by_role', $excludeRoles);
        }

        if ($authUserType === User::ADMIN_GROUP_ORG_ADMIN) {
            $query->whereJsonContains('organizations', $organization->id);
        }

        $orderByRoles = "'" . implode("','", User::ROLE_HIERARCHY) . "'";

        $mfaSettings = $query->orderByRaw('FIELD(created_by_role, ' . $orderByRoles . ')')->get();

        foreach ($mfaSettings as $mfa) {
            if (!MfaSettingHelper::isScopeMatched($mfaSetting, $mfa)) {
                continue;
            }

            $payload = [];

            if (MfaSettingHelper::validateMfaEnforcement($mfa, $mfaSetting->mfa_enforcement)) {
                $payload['mfa_enforcement'] = $mfaSetting->mfa_enforcement;
            }

            if ($authUserType === User::ADMIN_GROUP_ORG_ADMIN) {
                $payload['mfa_expiration_duration'] = $mfaSetting->mfa_expiration_duration ?? null;
                $payload['mfa_expiration_unit'] = $mfaSetting->mfa_expiration_unit ?? '';
                $payload['skip_mfa_setup_duration'] = $mfaSetting->skip_mfa_setup_duration ?? null;
                $payload['skip_mfa_setup_unit'] = $mfaSetting->skip_mfa_setup_unit ?? '';
            }

            if ($mfa->mfa_enforcement !== MfaSetting::MFA_RECOMMEND) {
                $payload['skip_mfa_setup_duration'] = null;
                $payload['skip_mfa_setup_unit'] = null;
            }

            if (!empty($payload)) {
                $mfa->update($payload);
            }
        }
    }

    public function applyToTherapistService($mfaSettingRole, $broadcastChannel, $jobId, $rowId, $isDeleting)
    {
        $token = Forwarder::getAccessToken(Forwarder::THERAPIST_SERVICE);
        Http::withToken($token)->acceptJson()->post(env('THERAPIST_SERVICE_URL') . '/internal/mfa-settings', [
            'role' => $mfaSettingRole,
            'broadcast_channel' => $broadcastChannel,
            'job_id' => $jobId,
            'row_id' => $rowId,
            'is_deleting' => $isDeleting
        ])->throw();
    }

    public function removeMfaForUsers($users)
    {
        foreach ($users as $user) {
            $keycloakUser = KeycloakHelper::getKeycloakUserByUsername($user->email);

            if (!$keycloakUser) {
                Log::warning("Keycloak user not found for email: {$user->email}");
                continue;
            }

            KeycloakHelper::setUserAttributes(
                $user->email,
                [
                    'mfaEnforcement' => '',
                    'trustedDeviceMaxAge' => '',
                    'skipMfaMaxAge' => '',
                    'skipMfaUntil' => '',
                ],
            );
        }
    }

    public function getMfaSettingsByUserType($userType, $mfaSettings)
    {
        $settings = collect($mfaSettings);

        $settings = $settings->filter(function ($item) use ($userType) {
            return $item->role === $userType;
        });

        $reverseRoles = array_reverse(User::ROLE_HIERARCHY);

        return $settings->sortBy(function ($item) use ($reverseRoles) {
            return array_search($item->created_by_role, $reverseRoles);
        })->values();
    }

    /**
     * Resolve the MFA setting for a user based on hierarchy + scope
     */
    public function resolve($mfaSettings, $user)
    {
        $matchedSetting = null;

        foreach ($mfaSettings as $mfa) {
            if ($mfa->role !== $user->type) {
                continue;
            }

            if (!empty($mfa->clinic_ids)) {
                if (in_array($user->clinic_id, $mfa->clinic_ids)) {
                    return $mfa;
                }
                continue;
            }

            if (!empty($mfa->phc_service_ids)) {
                if (in_array($user->phc_service_id, $mfa->phc_service_ids)) {
                    return $mfa;
                }
                continue;
            }

            if ($user->type === User::ADMIN_GROUP_REGIONAL_ADMIN && !empty($mfa->region_ids)) {
                if (count(array_intersect($user->regions->pluck('id')->toArray(), $mfa->region_ids)) > 0) {
                    return $mfa;
                }
                continue;
            } else if ($user->type !== User::ADMIN_GROUP_REGIONAL_ADMIN && !empty($mfa->region_ids)) {
                if (in_array($user->region_id, $mfa->region_ids)) {
                    return $mfa;
                }
                continue;
            }

            if (!empty($mfa->country_ids)) {
                if (in_array($user->country_id, $mfa->country_ids)) {
                    return $mfa;
                }
                continue;
            }

            if (
                empty($mfa->clinic_ids) &&
                empty($mfa->region_ids) &&
                empty($mfa->country_ids)
            ) {
                $matchedSetting = $mfa;
            }
        }

        return $matchedSetting;
    }

    public function apply($email, $mfaSetting): bool
    {
        $keycloakUser = KeycloakHelper::getKeycloakUserByUsername($email);

        if (!$keycloakUser) {
            Log::warning("Keycloak user not found for email: {$email}");
            return false;
        }

        $existingAttributes = $keycloakUser['attributes'] ?? [];

        $payload = [
            'mfaEnforcement' => $mfaSetting['mfa_enforcement'] ?? '',
            'trustedDeviceMaxAge' => $mfaSetting['mfa_expiration_duration_in_seconds'] ?? '',
            'skipMfaMaxAge' => $mfaSetting['skip_mfa_setup_duration_in_seconds'] ?? '',
        ];

        if (isset($existingAttributes['skipMfaUntil'])) {
            $date = Carbon::parse($existingAttributes['skipMfaUntil'][0]);

            $now = Carbon::now();

            $futureDate = $now->copy()->addSeconds($mfaSetting['skip_mfa_setup_duration_in_seconds']);

            $isoString = $futureDate->format('Y-m-d\TH:i:s.u\Z');

            if (!$date->isPast()) {
                $payload['skipMfaUntil'] = $isoString;
            }
        }

        KeycloakHelper::setUserAttributes($email, $payload);

        return true;
    }

    /**
     * Fetch users to apply MFA setting
     */
    public function getUsers()
    {
        $federatedDomains = array_map(fn($d) => strtolower(trim($d)), explode(',', env('FEDERATED_DOMAINS', '')));

        return User::query()
            ->where('email', '!=', 'hi_backend')
            ->where(function ($query) use ($federatedDomains) {
                foreach ($federatedDomains as $domain) {
                    $query->whereRaw('LOWER(email) NOT LIKE ?', ['%' . $domain]);
                }
            })
            ->get();
    }

    public function getMfaSettings($mfaSettingId = null)
    {
        if ($mfaSettingId) {
            return MfaSetting::where('id', '!=', $mfaSettingId)->whereJsonContains('organizations', MfaSettingHelper::getOrganization()->id)->get();
        }

        return MfaSetting::whereJsonContains('organizations', MfaSettingHelper::getOrganization()->id)->get();
    }

    public function validateMfaEnforcementForRegionalAdmin($regionId, $role)
    {
        $authUser = Auth::user();
        $reverseRoles = array_reverse(User::ROLE_HIERARCHY);
        $region = Region::findOrFail($regionId);

        $currentIndex = array_search($authUser->type, $reverseRoles);

        if ($currentIndex === false) {
            return null;
        }

        $organization = MfaSettingHelper::getOrganization();

        for ($i = $currentIndex + 1; $i < count($reverseRoles); $i++) {
            $parentRole = $reverseRoles[$i];

            $query = MfaSetting::where('role', $role)
                ->where('created_by_role', $parentRole)
                ->whereJsonContains('organizations', $organization->id);

            switch ($parentRole) {
                case User::ADMIN_GROUP_ORG_ADMIN:
                    $query->whereJsonContains('country_ids', (int) $region->country_id);
                    break;
                case User::ADMIN_GROUP_COUNTRY_ADMIN:
                    $query->whereJsonContains('region_ids', (int) $regionId);
                    break;
            }

            if ($setting = $query->first()) {
                return $setting;
            }
        }
    }
}
