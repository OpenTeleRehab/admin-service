<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Models\Clinic;
use App\Models\Country;
use App\Models\Organization;
use Illuminate\Http\Request;
use App\Helpers\KeycloakHelper;
use App\Helpers\LimitationHelper;
use Illuminate\Support\Facades\Http;
use App\Http\Resources\CountryResource;
use Illuminate\Support\Facades\Storage;
use Stevebauman\Location\Facades\Location;

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
     * @OA\Get(
     *     path="/api/country/{country}",
     *     summary="Get a single country",
     *     description="Returns a single country by ID",
     *     operationId="getCountryById",
     *     tags={"Countries"},
     *     @OA\Parameter(
     *         name="country",
     *         in="path",
     *         description="ID of the country to retrieve",
     *         required=true,
     *         @OA\Schema(
     *             type="integer",
     *             format="int64"
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Successful operation",
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(
     *                 property="data",
     *                 type="object",
     *                 ref="#/components/schemas/CountryResource"
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="Country not found",
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(property="message", type="string", example="No query results for model [App\\Models\\Country] 1")
     *         )
     *     )
     * )
     */
    public function show(Country $country)
    {
        return response()->json(['data' => new CountryResource($country)], 200);
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
     *         name="language_id",
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
     *     @OA\Parameter(
     *         name="phc_worker_limit",
     *         in="query",
     *         description="Phc worker limit",
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
        $validatedData = $request->validate([
            'iso_code' => 'required|string|max:10|unique:countries,iso_code',
            'name' => 'required|string|max:60',
            'phone_code' => 'required|string|max:10',
            'language_id' => 'nullable|exists:languages,id',
            'therapist_limit' => 'required|integer|min:0',
            'phc_worker_limit' => 'required|integer|min:0',
        ], [
            'iso_code.unique' => 'error_message.country_exists',
        ]);

        $orgLimitation = LimitationHelper::orgLimitation();

        if ($validatedData['therapist_limit'] > $orgLimitation['remaining_therapist_limit']) {
            abort(422, 'error.country.therapist_limit.greater_than.org_therapist_limit');
        }

        if ($validatedData['phc_worker_limit'] > $orgLimitation['remaining_phc_worker_limit']) {
            abort(422, 'error.country.phc_worker_limit.more_than.org_phc_worker_limit');
        }

        Country::create($validatedData);

        return response()->json(['success' => true, 'message' => 'success_message.country_add'], 200);
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
     *         name="language_id",
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
     *     @OA\Parameter(
     *         name="phc_worker_limit",
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
        $validatedData = $request->validate([
            'iso_code' => 'required|string|max:10|unique:countries,iso_code,' . $country->id,
            'name' => 'required|string|max:60',
            'phone_code' => 'required|string|max:10',
            'language_id' => 'nullable|exists:languages,id',
            'therapist_limit' => 'required|integer|min:0',
            'phc_worker_limit' => 'required|integer|min:0',
        ], [
            'iso_code.unique' => 'error_message.country_exists',
        ]);

        $orgLimitation = LimitationHelper::orgLimitation();
        $countryLimitation = LimitationHelper::countryLimitation($country->id);

        if ($validatedData['therapist_limit'] > $orgLimitation['remaining_therapist_limit'] + $country->therapist_limit) {
            abort(422, 'error.country.therapist_limit.greater_than.organization.therapist_limit');
        }

        if ($validatedData['therapist_limit'] < $countryLimitation['therapist_limit_used']) {
            abort(422, 'error.country.therapist_limit.less_than.region.theraist_limit');
        }

        if ($validatedData['phc_worker_limit'] > $orgLimitation['remaining_phc_worker_limit'] + $country->phc_worker_limit) {
            abort(422, 'error.country.phc_worker_limit.greater_than.organization.phc_worker_limit');
        }

        if ($validatedData['phc_worker_limit'] < $countryLimitation['phc_worker_limit_used']) {
            abort(422, 'error.country.phc_worker_limit.less_than.region.phc_worker_limit');
        }

        $country->update($validatedData);

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

            $userUrl = KeycloakHelper::getUserUrl() . '?email=' . $user->email;
            $response = Http::withToken($token)->get($userUrl);

            if ($response->successful()) {
                $keyCloakUsers = $response->json();

                $isDeleted = KeycloakHelper::deleteUser($token, KeycloakHelper::getUserUrl() . '/' . $keyCloakUsers[0]['id']);
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

    /**
     * Get the remaining limit for a the country.
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function limitation(Request $request)
    {
        $countryId = $request->query('country_id');

        return response()->json(['data' => LimitationHelper::countryLimitation($countryId)], 200);
    }
}
