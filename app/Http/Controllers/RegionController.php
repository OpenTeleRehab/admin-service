<?php

namespace App\Http\Controllers;

use App\Helpers\KeycloakHelper;
use App\Helpers\LimitationHelper;
use App\Http\Resources\EntitiesByRegionResource;
use App\Http\Resources\RegionResource;
use App\Models\Forwarder;
use App\Models\Region;
use App\Models\User;
use App\Models\UserSurvey;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class RegionController extends Controller
{
    /**
     * @OA\Get(
     *     path="/api/regions",
     *     tags={"Region"},
     *     summary="List of regions",
     *     operationId="getRegions",
     *     @OA\Parameter(
     *         name="filters",
     *         in="query",
     *         description="Filter regions, can be JSON string or query array",
     *         required=false,
     *         @OA\Schema(
     *             type="string",
     *             example="{\"country_id\":16}"
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Successful operation",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(
     *                 property="data",
     *                 type="array",
     *                 @OA\Items(
     *                     type="object",
     *                     @OA\Property(property="id", type="integer", example=1),
     *                     @OA\Property(property="country_id", type="integer", example=1),
     *                     @OA\Property(property="name", type="string", example="Region Name"),
     *                     @OA\Property(property="therapist_limit", type="integer", example=10),
     *                     @OA\Property(property="phc_worker_limit", type="integer", example=15)
     *                 )
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=500,
     *         description="Internal server error",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="Something went wrong")
     *         )
     *     )
     * )
     *
     * @param \Illuminate\Http\Request $request
     */
    public function index(Request $request)
    {
        $searchValue = $request->get('search_value');
        $pageSize = $request->get('page_size', 99999);
        if (Auth::user()->country_id) {
            $query = Auth::user()->country->regions();
        } else {
            $query = Region::query();
        }

        if ($searchValue) {
            $query->where('name', 'like', '%' . $searchValue . '%');
        }
        $regions = $query->paginate($pageSize);
        return response()->json(['success' => true, 'data' => RegionResource::collection($regions), 'total' => $regions->total(), 'current_page' => $regions->currentPage()]);
    }

    /**
     * @OA\Post(
     *     path="/api/regions",
     *     tags={"Region"},
     *     summary="Create a region",
     *     operationId="createRegion",
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"name","therapist_limit","phc_worker_limit"},
     *             @OA\Property(property="country_id", type="integer", description="Country ID (optional)"),
     *             @OA\Property(property="name", type="string", description="Region name"),
     *             @OA\Property(property="therapist_limit", type="integer", description="Therapist limit", minimum=0),
     *             @OA\Property(property="phc_worker_limit", type="integer", description="PHC worker limit", minimum=0)
     *         )
     *     ),
     *     @OA\Response(
     *         response=201,
     *         description="Region created successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="success_message.create.region")
     *         )
     *     ),
     *     @OA\Response(
     *         response=400,
     *         description="Bad request",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="Validation failed")
     *         )
     *     ),
     *     @OA\Response(
     *         response=401,
     *         description="Authentication is required"
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="Resource not found"
     *     ),
     *     @OA\Response(
     *         response=500,
     *         description="Internal server error",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="Something went wrong")
     *         )
     *     ),
     *     security={{"oauth2_security": {}}}
     * )
     *
     * @param \Illuminate\Http\Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function store(Request $request)
    {
        $country = Auth::user()->country;

        if (empty($country)) {
            abort(403, 'common.no.permission');
        }

        $validatedData = $request->validate([
            'name' => 'required|string|max:255',
            'therapist_limit' => 'required|integer|min:0',
            'phc_worker_limit' => 'required|integer|min:0',
        ]);

        $countryLimitation = LimitationHelper::countryLimitation($country->id);

        if ($validatedData['therapist_limit'] > $countryLimitation['remaining_therapist_limit']) {
            abort(422, 'region.therapist_limit.cannot_greater_than.country.therapist_limit');
        }

        if ($validatedData['phc_worker_limit'] > $countryLimitation['remaining_phc_worker_limit']) {
            abort(422, 'region.phc_worker_limit.cannot_greater_than.country.phc_worker_limit');
        }

        $validatedData['country_id'] = $country->id;

        Region::create($validatedData);

        return response()->json(['message' => 'region.success_message.add'], 201);
    }

    /**
     * @OA\Put(
     *     path="/api/regions/{id}",
     *     tags={"Region"},
     *     summary="Update the region",
     *     operationId="updateRegion",
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         description="Region ID",
     *         required=true,
     *         @OA\Schema(type="integer", example=1)
     *     ),
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             @OA\Property(property="name", type="string", description="Region name"),
     *             @OA\Property(property="therapist_limit", type="integer", description="Therapist limit", minimum=0),
     *             @OA\Property(property="phc_worker_limit", type="integer", description="PHC worker limit", minimum=0)
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Region updated successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="success_message.update.region")
     *         )
     *     ),
     *     @OA\Response(
     *         response=400,
     *         description="Bad request",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="Validation failed")
     *         )
     *     ),
     *     @OA\Response(
     *         response=401,
     *         description="Authentication is required"
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="Region not found"
     *     ),
     *     @OA\Response(
     *         response=500,
     *         description="Internal server error",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="Something went wrong")
     *         )
     *     ),
     *     security={{"oauth2_security": {}}}
     * )
     *
     * @param \Illuminate\Http\Request $request
     * @param \App\Models\Region $region
     * @return \Illuminate\Http\JsonResponse
     */
    public function update(Request $request, Region $region)
    {
        $country = Auth::user()->country;

        if (empty($country)) {
            abort(403, 'common.no.permission');
        }

        $validatedData = $request->validate([
            'name' => 'required|string|max:255',
            'therapist_limit' => 'required|integer|min:0',
            'phc_worker_limit' => 'required|integer|min:0',
        ]);

        $countryLimitation = LimitationHelper::countryLimitation($country->id);
        $regionLimitation = LimitationHelper::regionLimitation($region);
        $remainingTherapistLimitExcluded = $countryLimitation['remaining_therapist_limit'] + $region->therapist_limit;

        if ($validatedData['therapist_limit'] > $remainingTherapistLimitExcluded) {
            return response()->json([
                'message' => 'error.region.therapist_limit.greater_than.country.therapist_limit',
                'translate_params' => [
                    'allocated_therapist_limit' => $countryLimitation['allocated_therapist_limit'],
                    'remaining_therapist_limit' => $remainingTherapistLimitExcluded,
                    'therapist_limit_used' => $countryLimitation['therapist_limit_used'] - $region->therapist_limit,
                ]
            ], 422);
        }

        if ($validatedData['therapist_limit'] < $regionLimitation['therapist_limit_used']) {
            return response()->json([
                'message' => 'error.region.therapist_limit.less_than.provinces.total.therapist_limit',
                'translate_params' => [
                    'therapist_limit_used' => $countryLimitation['therapist_limit_used'],
                ]
            ], 422);
        }

        if ($validatedData['phc_worker_limit'] > $countryLimitation['remaining_phc_worker_limit'] + $country->phc_worker_limit) {
            return response()->json([
                'message' => 'error.region.phc_worker_limit.greater_than.country.phc_worker_limit',
                'translate_params' => [
                    'allocated_phc_worker_limit' => $countryLimitation['allocated_phc_worker_limit'],
                    'remaining_phc_worker_limit' => $countryLimitation['remaining_phc_worker_limit'] + $country->phc_worker_limit,
                    'phc_worker_limit_used' => $countryLimitation['phc_worker_limit_used'] - $country->phc_worker_limit,
                ]
            ], 422);
        }

        if ($validatedData['phc_worker_limit'] < $regionLimitation['phc_worker_limit_used']) {
            return response()->json([
                'message' => 'error.region.phc_worker_limit.less_than.provinces.total.phc_worker_limit',
                'translate_params' => [
                    'phc_worker_limit_used' => $countryLimitation['phc_worker_limit_used'],
                ]
            ], 422);
        }

        $region->update($validatedData);

        return response()->json(['message' => 'region.success_message.update'], 200);
    }

    /**
     * @OA\Delete(
     *     path="/api/regions/{id}",
     *     tags={"Region"},
     *     summary="Delete region",
     *     operationId="deleteRegion",
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         description="Region ID",
     *         required=true,
     *         @OA\Schema(type="integer", example=1)
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Region deleted successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="success_message.delete.region")
     *         )
     *     ),
     *     @OA\Response(
     *         response=400,
     *         description="Bad request",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="Validation failed")
     *         )
     *     ),
     *     @OA\Response(
     *         response=401,
     *         description="Authentication is required"
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="Region not found",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="Region not found")
     *         )
     *     ),
     *     @OA\Response(
     *         response=500,
     *         description="Internal server error",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="Something went wrong")
     *         )
     *     ),
     *     security={{"oauth2_security": {}}}
     * )
     *
     * @param \App\Models\Region $region
     * @return \Illuminate\Http\JsonResponse
     */
    public function destroy(Region $region)
    {
        $regionId = $region->id;
        $country = $region->country;

        $adminUsers = $region->users;

        $token = KeycloakHelper::getKeycloakAccessToken();

        foreach ($adminUsers as $user) {
            $response = Http::withToken($token)->get(
                KeycloakHelper::getUserUrl(),
                ['email' => $user->email]
            );

            if (!$response->successful()) {
                Log::error("Failed to fetch Keycloak user for {$user->email}. Status: {$response->status()}");
                continue;
            }

            $kcUserId = $response->json()[0]['id'] ?? null;

            if (!$kcUserId) {
                Log::warning("Keycloak user not found for email: {$user->email}");
                continue;
            }

            if (!KeycloakHelper::deleteUser($token, KeycloakHelper::getUserUrl() . '/' . $kcUserId)) {
                Log::error("Failed to delete Keycloak user for {$user->email}");
                continue;
            }

            UserSurvey::where('user_id', $user->id)->delete();
            $user->delete();
        }

        // Phone service
        Http::post(
            env('PHONE_SERVICE_URL') . '/data-clean-up/phones/bulk-delete',
            ['clinic_ids' => $regionId->clinics->pluck('id')]
        );

        // Therapist service
        Http::withToken(Forwarder::getAccessToken(Forwarder::THERAPIST_SERVICE))
            ->post(env('THERAPIST_SERVICE_URL') . '/data-clean-up/users/delete', [
                'entity_name' => 'region',
                'entity_id' => $regionId,
            ]);

        // Patient service
        Http::withHeaders([
            'Authorization' => 'Bearer ' . Forwarder::getAccessToken(
                    Forwarder::PATIENT_SERVICE,
                    $country->iso_code
                ),
            'country' => $country->iso_code,
        ])
            ->post(env('PATIENT_SERVICE_URL') . '/data-clean-up/users/delete', [
                'entity_name' => 'region',
                'entity_id' => $regionId,
            ]);

        $region->forceDelete();

        return response()->json(['message' => 'region.success_message.delete'], 200);
    }

    /**
     * Get the region limitation.
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function getLimitation(Request $request)
    {
        $regionId = $request->input('region_id');

        if ($regionId) {
            $region = Region::findOrFail($regionId);
        } else {
            $region = Auth::user()->region;
        }

        return response()->json(['data' => LimitationHelper::regionLimitation($region)], 200);
    }

    public function getRegionByCountry(Request $request)
    {
        $searchValue = $request->get('search_value');
        $pageSize = $request->get('page_size');
        $query = Auth::user()->country->regions();
        if ($searchValue) {
            $query->where('name', 'like', '%' . $searchValue . '%');
        }

        if ($pageSize) {
            $regions = $query->paginate($pageSize);

            return response()->json([
                'data' => RegionResource::collection($regions),
                'total' => $regions->total(),
                'current_page' => $regions->currentPage()
            ], 200);
        }

        return response()->json(['data' => RegionResource::collection($query->get())]);
    }

    /**
     * Retrieve the list of limitations for all regions within the authenticated user's country.
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function countryRegionLimitations()
    {
        $country = Auth::user()->country;

        $regions = $country->regions;

        $data = [];

        foreach ($regions as $region) {
            $regionLimitation = LimitationHelper::regionLimitation($region);

            $data[] = [
                'id' => $region->id,
                'name' => $region->name,
                'therapist_limit_used' => $regionLimitation['therapist_limit_used'],
                'remaining_therapist_limit' => $regionLimitation['remaining_therapist_limit'],
                'phc_worker_limit_used' => $regionLimitation['phc_worker_limit_used'],
                'remaining_phc_worker_limit' => $regionLimitation['remaining_phc_worker_limit'],
            ];
        }

        return response()->json(['data' => $data], 200);
    }


    /**
     * Get the counts of entities related to a specific region.
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function getEntitiesByRegionId(Region $region)
    {
        $region->load([
            'users',
            'provinces',
            'clinics',
            'phcServices',
        ]);

        $therapistPortalAccessToken = Forwarder::getAccessToken(Forwarder::THERAPIST_SERVICE);
        $patientPortalAccessToken = Forwarder::getAccessToken(Forwarder::PATIENT_SERVICE);

        $therapistCountRes = Http::withToken($therapistPortalAccessToken)->get(
            env('THERAPIST_SERVICE_URL') . '/data-clean-up/users/count',
            [
                'entity_name' => 'region',
                'entity_id' => $region->id,
                'user_type' => User::GROUP_THERAPIST,
            ]
        )->throw();

        $region->therapist_count = $therapistCountRes->json('data', 0);

        $phcWorkerCountRes = Http::withToken($therapistPortalAccessToken)->get(
            env('THERAPIST_SERVICE_URL') . '/data-clean-up/users/count',
            [
                'entity_name' => 'region',
                'entity_id' => $region->id,
                'user_type' => User::GROUP_PHC_WORKER,
            ]
        )->throw();

        $region->phc_worker_count = $phcWorkerCountRes->json('data', 0);

        $patientCountRes = Http::withToken($patientPortalAccessToken)->get(
            env('PATIENT_SERVICE_URL') . '/data-clean-up/users/count',
            [
                'entity_name' => 'region',
                'entity_id' => $region->id,
            ]
        )->throw();

        $region->patient_count = $patientCountRes->json('data', 0);

        return response()->json([
            'data' => new EntitiesByRegionResource($region),
        ]);
    }
}
