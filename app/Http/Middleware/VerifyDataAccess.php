<?php

namespace App\Http\Middleware;

use App\Models\Country;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use App\Models\User;

class VerifyDataAccess
{
  /**
   * Handle an incoming request.
   *
   * @param  \Illuminate\Http\Request  $request
   * @param  \Closure  $next
   * @return \Symfony\Component\HttpFoundation\Response
   */
    public function handle(Request $request, Closure $next): Response
    {
        $countryHeader = $request->header('Country');
        $countryId = $request->get('country_id') ?? $request->get('country');
        $clinicId = $request->get('clinic_id') ?? $request->get('clinic');

        /** @var User|null $user */
        $user = auth()->user();
        $accessDenied = false;

        // Null-safe early exit
        if (
            (!isset($countryHeader) && !isset($countryId) && !isset($clinicId)) ||
            ($user && $user->type === User::ADMIN_GROUP_ORG_ADMIN) ||
            ($user && $user->email === env('KEYCLOAK_BACKEND_CLIENT'))
        ) {
            return $next($request);
        }

        // Country ID check
        if ($user && $countryId) {
            $decodedCountryId = json_decode($countryId, true);
            $countryIds = is_array($decodedCountryId) ? $decodedCountryId : [$decodedCountryId];

            if (!empty($countryIds) && !in_array((int)$user->country_id, array_map('intval', $countryIds))) {
                $accessDenied = true;
            }
        }

        // Clinic ID check
        if ($user && $clinicId && $user->type !== User::ADMIN_GROUP_COUNTRY_ADMIN) {
            $decodedClinicId = json_decode($clinicId, true);
            $clinicIds = is_array($decodedClinicId) ? $decodedClinicId : [$decodedClinicId];

            if (!empty($clinicIds) && !in_array((int)$user->clinic_id, array_map('intval', $clinicIds))) {
                $accessDenied = true;
            }
        }

        // Country header check (normalize case)
        if ($countryHeader) {
            $country = Country::whereRaw('LOWER(iso_code) = ?', [strtolower($countryHeader)])->first();

            if (!$country) {
                return response()->json([
                    'message' => 'Invalid or unrecognized country.'
                ], 404);
            }

            if ($user && (int)$user->country_id !== (int)$country->id) {
                $accessDenied = true;
            }
        }

        // Final access decision
        if ($accessDenied) {
            return response()->json([
                'message' => 'Access denied'
            ], 403);
        }

        return $next($request);
    }
}
