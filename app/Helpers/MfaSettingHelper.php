<?php

namespace App\Helpers;

use App\Models\User;
use App\Models\MfaSetting;
use App\Models\Organization;

class MfaSettingHelper
{
    public static function isScopeMatched(MfaSetting $source, MfaSetting $target): bool
    {
        $has = fn($a, $b) => !empty(array_intersect($a ?? [], $b ?? []));

        return match ($source->created_by_role) {
            User::ADMIN_GROUP_ORG_ADMIN => $has($source->country_ids, $target->country_ids),

            User::ADMIN_GROUP_COUNTRY_ADMIN => $has($source->region_ids, $target->region_ids),

            User::ADMIN_GROUP_REGIONAL_ADMIN =>
            match ($source->role) {
                User::ADMIN_GROUP_PHC_SERVICE_ADMIN,
                User::GROUP_PHC_WORKER =>
                $has($source->phc_service_ids, $target->phc_service_ids),

                User::ADMIN_GROUP_CLINIC_ADMIN,
                User::GROUP_THERAPIST =>
                $has($source->clinic_ids, $target->clinic_ids),

                default => true,
            },

            User::ADMIN_GROUP_PHC_SERVICE_ADMIN => $has($source->phc_service_ids, $target->phc_service_ids),

            default => true,
        };
    }

    /**
     * MAIN LOGIC: Fetch the parent MFA setting based on hierarchy + scope
     */
    public static function getMfaSettingAboveRole(
        User $authUser,
        string $currentSettingRole,
    ) {
        $roleHierarchy = array_reverse(User::ROLE_HIERARCHY);

        $currentIndex = array_search($authUser->type, $roleHierarchy);

        if ($currentIndex === false) {
            return null;
        }

        $organization = MfaSettingHelper::getOrganization();

        for ($i = $currentIndex + 1; $i < count($roleHierarchy); $i++) {
            $parentRole = $roleHierarchy[$i];

            $query = MfaSetting::where('role', $currentSettingRole)
                ->where('created_by_role', $parentRole)
                ->whereJsonContains('organizations', $organization->id);

            switch ($parentRole) {
                case User::ADMIN_GROUP_ORG_ADMIN:
                    $query->whereJsonContains('country_ids', $authUser->country_id);
                    break;
                case User::ADMIN_GROUP_COUNTRY_ADMIN:
                    if ($authUser->type === User::ADMIN_GROUP_REGIONAL_ADMIN) {
                        $query->where(function ($q) use ($authUser) {
                            foreach ($authUser->regions->pluck('id')->toArray() as $id) {
                                $q->orWhereJsonContains('region_ids', $id);
                            }
                        });
                    } else {
                        $query->whereJsonContains('region_ids', $authUser->region_id);
                    }
                    break;
                case User::ADMIN_GROUP_REGIONAL_ADMIN:
                    if ($authUser->type === User::ADMIN_GROUP_CLINIC_ADMIN) {
                        $query->whereJsonContains('clinic_ids', $authUser->clinic_id);
                    } else if ($authUser->type === User::ADMIN_GROUP_PHC_SERVICE_ADMIN) {
                        $query->whereJsonContains('phc_service_ids', $authUser->phc_service_id);
                    }
                    break;
            }

            if ($setting = $query->first()) {
                return $setting;
            }
        }

        return null;
    }

    /**
     * Validate that the new MFA enforcement is >= parent's enforcement
     * (kept as static for consistency)
     */
    public static function validateMfaEnforcement(?MfaSetting $parentSetting, string $newEnforcement): bool
    {
        if (!$parentSetting) {
            return true;
        }

        $childLevel = self::checkMfaEnforcementLevel($newEnforcement);
        $parentLevel = self::checkMfaEnforcementLevel($parentSetting->mfa_enforcement);

        return $childLevel >= $parentLevel;
    }

    public static function checkMfaEnforcementLevel(string $mfaEnforcement): int
    {
        return match ($mfaEnforcement) {
            MfaSetting::MFA_DISABLE   => 1,
            MfaSetting::MFA_RECOMMEND => 2,
            MfaSetting::MFA_ENFORCE   => 3,
            default => 0,
        };
    }

    /**
     * Get current organization based on APP_NAME
     */
    public static function getOrganization()
    {
        return Organization::where('sub_domain_name', env('APP_NAME'))->first();
    }
}
