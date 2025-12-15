<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Models\Clinic;
use App\Models\Country;
use App\Models\Forwarder;
use Illuminate\Http\Request;
use App\Helpers\KeycloakHelper;
use App\Helpers\LimitationHelper;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Http;
use App\Http\Resources\ClinicResource;

define("KEYCLOAK_USERS", env('KEYCLOAK_URL') . '/auth/admin/realms/' . env('KEYCLOAK_REAMLS_NAME') . '/users');

class ClinicController extends Controller
{
    /**
     * @OA\Get(
     *     path="/api/clinic",
     *     tags={"Clinic"},
     *     summary="Lists all clinics",
     *     operationId="clinicList",
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
        $clinics = Auth::user()->region->clinics;

        return ['success' => true, 'data' => ClinicResource::collection($clinics)];
    }

    /**
     * @OA\Post(
     *     path="/api/clinic",
     *     tags={"Clinic"},
     *     summary="Create clinic",
     *     operationId="createClinic",
     *     @OA\Parameter(
     *         name="name",
     *         in="query",
     *         description="Clinic name",
     *         required=true,
     *         @OA\Schema(
     *             type="string"
     *         )
     *     ),
     *     @OA\Parameter(
     *         name="country",
     *         in="query",
     *         description="Country id",
     *         required=true,
     *         @OA\Schema(
     *             type="integer"
     *         )
     *     ),
     *     @OA\Parameter(
     *         name="region_id",
     *         in="query",
     *         description="Region",
     *         required=true,
     *         @OA\Schema(
     *             type="integer"
     *         )
     *     ),
     *     @OA\Parameter(
     *         name="province_id",
     *         in="query",
     *         description="Province",
     *         required=false,
     *         @OA\Schema(
     *             type="integer"
     *         )
     *     ),
     *     @OA\Parameter(
     *         name="city",
     *         in="query",
     *         description="City",
     *         required=true,
     *         @OA\Schema(
     *             type="string"
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
        $validatedData = $request->validate([
            'name' => 'required|string|max:255',
            'country_id' => 'required|exists:countries,id',
            'region_id' => 'required|exists:regions,id',
            'province_id' => 'nullable|exists:provinces,id',
            'city' => 'required|string|max:255',
            'therapist_limit' => 'required|integer|min:0',
            'phone' => 'nullable|string|max:20',
            'dial_code' => 'nullable|string|max:10',
        ]);

        $provinceLimitation = LimitationHelper::provinceLimitation($validatedData['province_id']);

        if ($validatedData['therapist_limit'] > $provinceLimitation['remaining_therapist_limit']) {
            abort(422, 'error.clinic.therapist_limit.greater_than.province.therapist_limit');
        }

        Clinic::create($validatedData);

        return ['success' => true, 'message' => 'success_message.clinic_add'];
    }

    /**
     * @OA\Put(
     *     path="/api/clinic/{id}",
     *     tags={"Clinic"},
     *     summary="Update clinic",
     *     operationId="updateClinic",
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         description="Clinic id",
     *         required=true,
     *         @OA\Schema(
     *             type="integer"
     *         )
     *     ),
     *     @OA\Parameter(
     *         name="name",
     *         in="query",
     *         description="Clinic name",
     *         required=true,
     *         @OA\Schema(
     *             type="string"
     *         )
     *     ),
     *     @OA\Parameter(
     *         name="country",
     *         in="query",
     *         description="Country id",
     *         required=true,
     *         @OA\Schema(
     *             type="integer"
     *         )
     *     ),
     *     @OA\Parameter(
     *         name="region",
     *         in="query",
     *         description="Region",
     *         required=true,
     *         @OA\Schema(
     *             type="string"
     *         )
     *     ),
     *     @OA\Parameter(
     *         name="province",
     *         in="query",
     *         description="Province",
     *         required=false,
     *         @OA\Schema(
     *             type="string"
     *         )
     *     ),
     *     @OA\Parameter(
     *         name="city",
     *         in="query",
     *         description="City",
     *         required=true,
     *         @OA\Schema(
     *             type="string"
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
     * @param \App\Models\Clinic $clinic
     *
     * @return array
     */
    public function update(Request $request, Clinic $clinic)
    {
        $validatedData = $request->validate([
            'name' => 'required|string|max:255',
            'region_id' => 'required|exists:regions,id',
            'province_id' => 'nullable|exists:provinces,id',
            'city' => 'required|string|max:255',
            'therapist_limit' => 'required|integer|min:0',
            'phone' => 'nullable|string|max:20',
            'dial_code' => 'nullable|string|max:10',
        ]);

        $provinceLimitation = LimitationHelper::provinceLimitation($validatedData['province_id']);
        $totalTherapist = $this->findTotalTherapistByClinic($clinic->id);

        if ($validatedData['province_id'] !== $clinic->province_id && $validatedData['therapist_limit'] > $provinceLimitation['remaining_therapist_limit']) {
            return response()->json([
                'message' => 'error.clinic.therapist_limit.greater_than.province.therapist_limit',
                'translate_params' => [
                    'allocated_therapist_limit' => $provinceLimitation['allocated_therapist_limit'],
                    'remaining_therapist_limit' => $provinceLimitation['remaining_therapist_limit'],
                    'therapist_limit_used' => $provinceLimitation['therapist_limit_used'],
                ]
            ], 422);
        }

        if ($validatedData['therapist_limit'] > $provinceLimitation['remaining_therapist_limit'] + $clinic->therapist_limit) {
            return response()->json([
                'message' => 'error.clinic.therapist_limit.greater_than.province.therapist_limit',
                'translate_params' => [
                    'allocated_therapist_limit' => $provinceLimitation['allocated_therapist_limit'],
                    'remaining_therapist_limit' => $provinceLimitation['remaining_therapist_limit'] + $clinic->therapist_limit,
                    'therapist_limit_used' => $provinceLimitation['therapist_limit_used'] - $clinic->therapist_limit,
                ]
            ], 422);
        }

        if ($totalTherapist > $validatedData['therapist_limit']) {
            abort(422, 'error.clinic.therapist_limit.less_than.total.therapist');
        }

        $clinic->update($validatedData);

        return ['success' => true, 'message' => 'success_message.clinic_update'];
    }

    /**
     * @OA\Delete(
     *     path="/api/clinic/{id}",
     *     tags={"Clinic"},
     *     summary="Delete clinic",
     *     operationId="DeleteClinic",
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         description="Clinic id",
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
     * @param \App\Models\Clinic $clinic
     *
     * @return array
     * @throws \Exception
     */
    public function destroy(Clinic $clinic)
    {
        if (!$clinic->is_used) {
            $country = Country::where('id', $clinic->country_id)->first();

            // Remove clinic admin users.
            $users = User::where('type', User::ADMIN_GROUP_CLINIC_ADMIN)
                ->where('clinic_id', $clinic->id)->get();

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

            // Remove therapists of clinic.
            Http::withToken(Forwarder::getAccessToken(Forwarder::THERAPIST_SERVICE))
                ->post(env('THERAPIST_SERVICE_URL') . '/therapist/delete/by-clinic', [
                    'clinic_id' => $clinic->id,
                ]);

            // Remove patients of clinic.
            Http::withHeaders([
                'Authorization' => 'Bearer ' . Forwarder::getAccessToken(Forwarder::PATIENT_SERVICE, $country->iso_code),
                'country' => $country->iso_code,
            ])->post(env('PATIENT_SERVICE_URL') . '/patient/delete/by-clinic', [
                'clinic_id' => $clinic->id,
            ]);

            $clinic->delete();
            return ['success' => true, 'message' => 'success_message.clinic_delete'];
        }

        return ['success' => false, 'message' => 'error_message.clinic_delete'];
    }

    /**
     * @OA\Get(
     *     path="/api/clinic/therapist-limit/count/by-country",
     *     tags={"Clinic"},
     *     summary="Total therapist limit by country",
     *     operationId="totalTherapistLimit",
     *     @OA\Parameter(
     *         name="country_id",
     *         in="query",
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
     * @param Request $request
     * @return array
     */
    public function countTherapistLimitByCountry(Request $request)
    {
        $countryId = $request->get('country_id');
        $therapistLimitTotal = DB::table('clinics')
            ->select(DB::raw('
                SUM(therapist_limit) AS total
            '))
            ->where('country_id', $countryId)
            ->get()->first();

        return [
            'success' => true,
            'data' => $therapistLimitTotal
        ];
    }

    /**
     * @OA\Get(
     *     path="/api/clinic/therapist/count/by-clinic",
     *     tags={"Clinic"},
     *     summary="Total therapist by clinic",
     *     operationId="totalTherapistByClinic",
     *     @OA\Parameter(
     *         name="clinic_id",
     *         in="query",
     *         description="Clinic id",
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
     * @param Request $request
     * @return array
     */
    public function countTherapistByClinic(Request $request)
    {
        $clinicId = $request->get('clinic_id');

        $therapistData = [];
        $response = Http::withToken(Forwarder::getAccessToken(Forwarder::THERAPIST_SERVICE))
            ->get(env('THERAPIST_SERVICE_URL') . '/chart/get-data-for-clinic-admin', [
                'clinic_id' => [$clinicId]
            ]);

        if (!empty($response) && $response->successful()) {
            $therapistData = $response->json();
        }

        return [
            'success' => true,
            'data' => $therapistData
        ];
    }

    /**
     * Display the specified resource.
     *
     * @param Clinic $clinic
     *
     * @return ClinicResource
     */
    public function getById(Clinic $clinic)
    {
        return new ClinicResource($clinic);
    }

    private function findTotalTherapistByClinic($clinicId)
    {
        $response = Http::withToken(Forwarder::getAccessToken(Forwarder::THERAPIST_SERVICE))
            ->get(env('THERAPIST_SERVICE_URL') . '/chart/get-data-for-clinic-admin', [
                'clinic_id' => [$clinicId]
            ]);

        if (!empty($response) && $response->successful()) {
            return $response->json()['therapistTotal'];
        }

        return 0;
    }
}
