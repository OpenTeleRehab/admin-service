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
        $user = auth()->user();
        $accessDenied = false;

        // If following condition is met, skip validation and allow request
        if ((!isset($countryHeader) && !isset($countryId) && !isset($clinicId)) || $user->type === User::ADMIN_GROUP_ORG_ADMIN || $user->email === env('KEYCLOAK_BACKEND_CLIENT')) {
            return $next($request);
        }

        // Verify if the auth user belongs to their assigned country
        if ($user && $countryId) {
            $decodedCountryId = json_decode($countryId, true);
            if (is_array($decodedCountryId)) {
                if (!empty($decodedCountryId) && !in_array($user->country_id, $decodedCountryId)) {
                    $accessDenied = true;
                }
            } else if ((int)$user->country_id !== (int)$decodedCountryId) {
                $accessDenied = true;
            }
        }

        // Verify if the auth user belongs to their assigned clinic
        if ($user && $clinicId && $user->type !== User::ADMIN_GROUP_COUNTRY_ADMIN) {
            $decodeClinicId = json_decode($clinicId, true);
            if (is_array($decodeClinicId)) {
                if (!empty($decodeClinicId) && !in_array($user->clinic_id, $decodeClinicId)) {
                    $accessDenied = true;
                }
            } else if ((int)$user->clinic_id !== (int)$decodeClinicId) {
                $accessDenied = true;
            }
        }

        // Verify if the auth user belongs to the country base on the country header provided
        if (isset($countryHeader)) {
            $country = Country::where('iso_code', $countryHeader)->first();
            if (!$country || !$country->id) {
                return response()->json([
                    'message' => 'Invalid or unrecognized country.'
                ], 404);
            }

            if ($user && (int)$user->country_id !== (int)$country->id) {
                $accessDenied = true;
            }
        }

        if ($accessDenied) {
            return response()->json([
                'message' => 'Access denied'
            ], 403);
        }

        return $next($request);
    }
}