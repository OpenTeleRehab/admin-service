<?php

namespace App\Helpers;

use App\Models\Country;
use App\Models\Province;
use App\Models\Organization;
use Illuminate\Support\Facades\Auth;

class LimitationHelper
{
    /**
     * Get the limitation for a the organization.
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public static function orgLimitation()
    {
        $organization = Organization::where('sub_domain_name', env('APP_NAME'))->firstOrFail();

        $countries = Country::all();

        $therapistLimitUsed = $countries->sum('therapist_limit');
        $phcWorkerLimitUsed = $countries->sum('phc_worker_limit');

        $remainingTherapistLimit = $organization->max_number_of_therapist - $therapistLimitUsed;
        $remainingPhcWorkerLimit = $organization->max_number_of_phc_worker - $phcWorkerLimitUsed;

        return [
            'therapist_limit_used' => $therapistLimitUsed,
            'remaining_therapist_limit' => $remainingTherapistLimit,
            'phc_worker_limit_used' => $phcWorkerLimitUsed,
            'remaining_phc_worker_limit' => $remainingPhcWorkerLimit,
        ];
    }

    /**
     * Get the limitation for a the country.
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public static function countryLimitation($countryId = null)
    {
        $country = Auth::user()->country;

        if (!$country) {
            $country = Country::findOrFail($countryId);
        }

        $therapistLimitUsed = $country->regions->sum('therapist_limit');
        $phcWorkerLimitUsed = $country->regions->sum('phc_worker_limit');

        $remainingTherapistLimit = $country->therapist_limit - $therapistLimitUsed;
        $remainingPhcWorkerLimit = $country->phc_worker_limit - $phcWorkerLimitUsed;

        return [
            'therapist_limit_used' => $therapistLimitUsed,
            'remaining_therapist_limit' => $remainingTherapistLimit,
            'phc_worker_limit_used' => $phcWorkerLimitUsed,
            'remaining_phc_worker_limit' => $remainingPhcWorkerLimit,
        ];
    }

    /**
     * Get the limitation for a the region.
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public static function regionLimitation($region)
    {
        $therapistLimitUsed = $region->provinces->sum('therapist_limit');
        $phcWorkerLimitUsed = $region->provinces->sum('phc_worker_limit');

        $remainingTherapistLimit = $region->therapist_limit - $therapistLimitUsed;
        $remainingPhcWorkerLimit = $region->phc_worker_limit - $phcWorkerLimitUsed;
        return [
            'therapist_limit_used' => $therapistLimitUsed,
            'remaining_therapist_limit' => $remainingTherapistLimit,
            'phc_worker_limit_used' => $phcWorkerLimitUsed,
            'remaining_phc_worker_limit' => $remainingPhcWorkerLimit,
        ];
    }

    /**
     * Get the limitation for a the province.
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public static function provinceLimitation($provinceId)
    {
        $province = Province::findOrFail($provinceId);
        $therapistLimitUsed = $province->clinics->sum('therapist_limit');
        $phcWorkerLimitUsed = $province->clinics->sum('phc_worker_limit');
        $remainingTherapistLimit = $province->therapist_limit - $therapistLimitUsed;
        $remainingPhcWorkerLimit = $province->phc_worker_limit - $phcWorkerLimitUsed;

        return [
            'therapist_limit_used' => $therapistLimitUsed,
            'remaining_therapist_limit' => $remainingTherapistLimit,
            'phc_worker_limit_used' => $phcWorkerLimitUsed,
            'remaining_phc_worker_limit' => $remainingPhcWorkerLimit,
        ];
    }

    public static function clinicLimitation($clinic)
    {
        $therapistLimitUsed = $clinic->therapists()->count();
        $phcWorkerLimitUsed = $clinic->phcWorkers()->count();
        $remainingTherapistLimit = max(0, $clinic->therapist_limit - $therapistLimitUsed);
        $remainingPhcWorkerLimit = max(0, $clinic->phc_worker_limit - $phcWorkerLimitUsed);

        return [
            'therapist_limit_used' => $therapistLimitUsed,
            'remaining_therapist_limit' => $remainingTherapistLimit,
            'phc_worker_limit_used' => $phcWorkerLimitUsed,
            'remaining_phc_worker_limit' => $remainingPhcWorkerLimit,
        ];
    }
}
