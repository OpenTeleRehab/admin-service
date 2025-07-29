<?php

namespace App\Http\Controllers;

use App\Helpers\KeycloakHelper;
use App\Http\Resources\CountryResource;
use App\Models\Clinic;
use App\Models\Country;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Stevebauman\Location\Facades\Location;

define("KEYCLOAK_USERS", env('KEYCLOAK_URL') . '/auth/admin/realms/' . env('KEYCLOAK_REAMLS_NAME') . '/users');

class CountryController extends Controller
{
    /**
     * @OA\Get(
     *     path="/api/country",
     *     tags={"Country"},
     *     summary="Lists all countries",
     *     operationId="countryList",
     *     @OA\Response(
     *         response="200",
     *         description="successful operation"
     *     ),
     *     @OA\Response(response=400, description="Bad request"),
     *     @OA\Response(response=404, description="Resource Not Found"),
     *     @OA\Response(response=401, description="Authentication is required"),
     *     security={
     *         {
     *             "oauth2_security": {}
     *         }
     *     },
     * )
     *
     * @return array
     */
    public function index()
    {
        $countries = Country::all();
        $userCountryCode = null;
        $clientIps = explode(',', \request()->ip());
        $publicIp = trim(current($clientIps));
        if ($publicIp && $position = Location::get($publicIp)) {
            $userCountryCode = $position->countryCode;
        }

        return [
            'success' => true,
            'data' => CountryResource::collection($countries),
            'user_country_code' => $userCountryCode,
        ];
    }

    /**
     * @OA\Post(
     *     path="/api/country",
     *     tags={"Country"},
     *     summary="Create country",
     *     operationId="createCountry",
     *     @OA\Parameter(
     *         name="name",
     *         in="query",
     *         description="Country name",
     *         required=true,
     *         @OA\Schema(
     *             type="string"
     *         )
     *     ),
     *     @OA\Parameter(
     *         name="iso_code",
     *         in="query",
     *         description="ISO code",
     *         required=true,
     *         @OA\Schema(
     *             type="string"
     *         )
     *     ),
     *     @OA\Parameter(
     *         name="phone_code",
     *         in="query",
     *         description="Phone code",
     *         required=true,
     *         @OA\Schema(
     *             type="string"
     *         )
     *     ),
     *     @OA\Parameter(
     *         name="language",
     *         in="query",
     *         description="Language id",
     *         required=false,
     *         @OA\Schema(
     *             type="integer"
     *         )
     *     ),
     *     @OA\Parameter(
     *         name="therapist_limit",
     *         in="query",
     *         description="Therapist limit",
     *         required=true,
     *         @OA\Schema(
     *             type="integer"
     *         )
     *     ),
     *     @OA\Response(
     *         response="200",
     *         description="successful operation"
     *     ),
     *     @OA\Response(response=400, description="Bad request"),
     *     @OA\Response(response=404, description="Resource Not Found"),
     *     @OA\Response(response=401, description="Authentication is required"),
     *     security={
     *         {
     *             "oauth2_security": {}
     *         }
     *     },
     * )
     *
     * @param \Illuminate\Http\Request $request
     *
     * @return array|void
     */
    public function store(Request $request)
    {
        $isoCode = $request->get('iso_code');
        $availableCountry = Country::where('iso_code', $isoCode)->count();
        if ($availableCountry) {
            return abort(409, 'error_message.country_exists');
        }

        Country::create([
            'iso_code' => $isoCode,
            'name' => $request->get('name'),
            'phone_code' => $request->get('phone_code'),
            'language_id' => $request->get('language'),
            'therapist_limit' => $request->get('therapist_limit')
        ]);

        return ['success' => true, 'message' => 'success_message.country_add'];
    }

    /**
     * @OA\Put(
     *     path="/api/country/{id}",
     *     tags={"Country"},
     *     summary="Update country",
     *     operationId="updateCountry",
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         description="Country id",
     *         required=true,
     *         @OA\Schema(
     *             type="integer"
     *         )
     *     ),
     *     @OA\Parameter(
     *         name="name",
     *         in="query",
     *         description="Country name",
     *         required=true,
     *         @OA\Schema(
     *             type="string"
     *         )
     *     ),
     *     @OA\Parameter(
     *         name="iso_code",
     *         in="query",
     *         description="ISO code",
     *         required=true,
     *         @OA\Schema(
     *             type="string"
     *         )
     *     ),
     *     @OA\Parameter(
     *         name="phone_code",
     *         in="query",
     *         description="Phone code",
     *         required=true,
     *         @OA\Schema(
     *             type="string"
     *         )
     *     ),
     *     @OA\Parameter(
     *         name="language",
     *         in="query",
     *         description="Language id",
     *         required=false,
     *         @OA\Schema(
     *             type="integer"
     *         )
     *     ),
     *     @OA\Parameter(
     *         name="therapist_limit",
     *         in="query",
     *         description="Therapist limit",
     *         required=true,
     *         @OA\Schema(
     *             type="integer"
     *         )
     *     ),
     *     @OA\Response(
     *         response="200",
     *         description="successful operation"
     *     ),
     *     @OA\Response(response=400, description="Bad request"),
     *     @OA\Response(response=404, description="Resource Not Found"),
     *     @OA\Response(response=401, description="Authentication is required"),
     *     security={
     *         {
     *             "oauth2_security": {}
     *         }
     *     },
     * )
     *
     * @param \Illuminate\Http\Request $request
     * @param \App\Models\Country $country
     *
     * @return array|void
     */
    public function update(Request $request, Country $country)
    {
        $isoCode = $request->get('iso_code');
        $availableCountry = Country::where('id', '<>', $country->id)
            ->where('iso_code', $isoCode)
            ->count();
        if ($availableCountry) {
            return abort(409, 'error_message.country_exists');
        }

        $country->update([
            'iso_code' => $isoCode,
            'name' => $request->get('name'),
            'phone_code' => $request->get('phone_code'),
            'language_id' => $request->get('language'),
            'therapist_limit' => $request->get('therapist_limit')
        ]);

        return ['success' => true, 'message' => 'success_message.country_update'];
    }

    /**
     * @OA\Delete(
     *     path="/api/country/{id}",
     *     tags={"Country"},
     *     summary="Delete country",
     *     operationId="deleteCountry",
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         description="Country id",
     *         required=true,
     *         @OA\Schema(
     *             type="integer"
     *         )
     *     ),
     *     @OA\Response(
     *         response="200",
     *         description="successful operation"
     *     ),
     *     @OA\Response(response=400, description="Bad request"),
     *     @OA\Response(response=404, description="Resource Not Found"),
     *     @OA\Response(response=401, description="Authentication is required"),
     *     security={
     *         {
     *             "oauth2_security": {}
     *         }
     *     },
     * )
     *
     * @param \App\Models\Country $country
     *
     * @return array
     * @throws \Exception
     */
    public function destroy(Country $country)
    {
        $users = User::where('type', User::ADMIN_GROUP_COUNTRY_ADMIN)
            ->where('country_id', $country->id)->get();

        /** @var \App\Models\User $user */
        foreach ($users as $user) {
            $token = KeycloakHelper::getKeycloakAccessToken();

            $userUrl = KEYCLOAK_USERS . '?email=' . $user->email;
            $response = Http::withToken($token)->get($userUrl);

            if ($response->successful()) {
                $keyCloakUsers = $response->json();

                $isDeleted = KeycloakHelper::deleteUser($token, KEYCLOAK_USERS . '/' . $keyCloakUsers[0]['id']);
                if ($isDeleted) {
                    $user->delete();
                }
            }
        }

        $clinics = Clinic::where('country_id', $country->id)->get();
        foreach ($clinics as $clinic) {
            // Remove clinics and related objects of country.
            Http::delete(env("ADMIN_SERVICE_URL") . "/clinic/$clinic->id");
        }

        $country->delete();
        return ['success' => true, 'message' => 'success_message.country_delete'];
    }

    /**
     * @return array
     */
    public function getDefinedCountries()
    {
        $json = Storage::get("country/countries.json");
        $data = json_decode($json, TRUE) ?? [];
        return [
            'success' => true,
            'data' => $data
        ];
    }

    /**
     * @param \Illuminate\Http\Request $request
     *
     * @return mixed
     */
    public function getCountryByClinicId(Request $request)
    {
        $clinic = Clinic::find($request->get('clinic_id'));
        $country = Country::find($clinic->country_id);

        return ['success' => true, 'data' => $country];
    }

    /**
     * @param \Illuminate\Http\Request $request
     *
     * @return array
     */
    public function getCountryByIsoCode(Request $request)
    {
        $isoCode = $request->get('iso_code');
        $country = Country::where('iso_code', $isoCode)->first();

        if (!$country) {
            return response()->json(['message' => 'Country not found'], 404);
        }

        return ['success' => true, 'data' => $country];
    }
}
