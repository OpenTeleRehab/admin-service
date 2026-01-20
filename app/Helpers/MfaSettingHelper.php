<?php

namespace App\Helpers;

use App\Models\User;
use App\Models\MfaSetting;
use App\Models\Organization;

class MfaSettingHelper
{
    /**
     * MAIN LOGIC: Fetch the parent MFA setting based on hierarchy + scope
     */
    public static function getMfaSettingAboveRole(
        User $authUser,
        string $currentSettingRole,
    ) {
        $roleHierarchy = [
            User::ADMIN_GROUP_PHC_SERVICE_ADMIN,
            User::ADMIN_GROUP_CLINIC_ADMIN,
            User::ADMIN_GROUP_REGIONAL_ADMIN,
            User::ADMIN_GROUP_COUNTRY_ADMIN,
            User::ADMIN_GROUP_ORG_ADMIN,
            User::ADMIN_GROUP_SUPER_ADMIN,
        ];

        $currentIndex = array_search($authUser->type, $roleHierarchy);

        if ($currentIndex === false) {
            return null;
        }

        $hiOrganization = Organization::where('sub_domain_name', env('APP_NAME'))->first();

        for ($i = $currentIndex + 1; $i < count($roleHierarchy); $i++) {
            $parentRole = $roleHierarchy[$i];

            $query = MfaSetting::where('role', $currentSettingRole)
                ->where('created_by_role', $parentRole)
                ->whereJsonContains('organizations', $hiOrganization->id);

            switch ($parentRole) {
                case User::ADMIN_GROUP_ORG_ADMIN:
                    $query->whereJsonContains('country_ids', $authUser->country_id);
                    break;
                case User::ADMIN_GROUP_COUNTRY_ADMIN:
                    $query->whereJsonContains('region_ids', $authUser->region_id);
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
}
