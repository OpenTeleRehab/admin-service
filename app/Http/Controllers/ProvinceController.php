<?php

namespace App\Http\Controllers;

use App\Helpers\KeycloakHelper;
use App\Helpers\LimitationHelper;
use App\Http\Resources\EntitiesByProvinceResource;
use App\Http\Resources\ProvinceResource;
use App\Models\Forwarder;
use App\Models\Province;
use App\Models\User;
use App\Models\UserSurvey;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class ProvinceController extends Controller
{
    /**
     * @OA\Get(
     *     path="/api/provinces",
     *     tags={"Province"},
     *     summary="List of provinces",
     *     operationId="getProvinces",
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
     *                     @OA\Property(property="region_id", type="integer", example=1),
     *                     @OA\Property(property="name", type="string", example="Province Name"),
     *                     @OA\Property(property="therapist_limit", type="integer", example=10),
     *                     @OA\Property(property="phc_worker_limit", type="integer", example=15)
     *                 )
     *             )
     *         )
     *     )
     * )
     *
     * @param \Illuminate\Http\Request $request
     */
    public function index(Request $request)
    {
        $user = Auth::user();
        $query = Province::query();
        $searchValue = $request->get('search_value');
        $pageSize = $request->get('page_size', 999999);

        if ($user->type === User::ADMIN_GROUP_COUNTRY_ADMIN) {
            $query->whereHas('region', function ($q) use ($user) {
                $q->where('country_id', $user->country_id);
            });
        }

        if ($user->type === User::ADMIN_GROUP_REGIONAL_ADMIN) {
            $regionIds = $user->regions->pluck('id')->toArray();
            $query = Province::whereIn('region_id', $regionIds);
        }

        if ($searchValue) {
            $query->where('name', 'like', '%' . $searchValue . '%');
        }

        $provinces = $query->paginate($pageSize);

        return response()->json(['success' => true, 'data' => ProvinceResource::collection($provinces), 'total' => $provinces->total(), 'current_page' => $provinces->currentPage()]);
    }

    /**
     * @OA\Get(
     *     path="/api/provinces-by-region",
     *     tags={"Province"},
     *     summary="List of all provinces by user region",
     *     operationId="getAllProvincesByRegion",
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
     *                     @OA\Property(property="region_id", type="integer", example=1),
     *                     @OA\Property(property="name", type="string", example="Province Name"),
     *                     @OA\Property(property="therapist_limit", type="integer", example=10),
     *                     @OA\Property(property="phc_worker_limit", type="integer", example=15)
     *                 )
     *             )
     *         )
     *     )
     * )
     */
    public function getByUserRegion()
    {
        $user = Auth::user();
        $limitatedRegionIds = $user->regions->pluck('id')->toArray();
        if ($user->region_id) {
            $limitatedRegionIds[] = $user->region_id;
        }
        $limitatedRegionIds = array_unique($limitatedRegionIds);

        $provinces = Province::whereIn('region_id', $limitatedRegionIds)->get();
        return response()->json(['data' => ProvinceResource::collection($provinces)]);
    }

    /**
     * @OA\Post(
     *     path="/api/provinces",
     *     tags={"Province"},
     *     summary="Create a province",
     *     operationId="createProvince",
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"name","therapist_limit","phc_worker_limit"},
     *             @OA\Property(property="name", type="string", description="Province name"),
     *             @OA\Property(property="therapist_limit", type="integer", description="Therapist limit", minimum=1),
     *             @OA\Property(property="phc_worker_limit", type="integer", description="PHC worker limit", minimum=1)
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Successful operation",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean"),
     *             @OA\Property(property="message", type="string")
     *         )
     *     ),
     *     @OA\Response(response=400, description="Bad request"),
     *     @OA\Response(response=401, description="Authentication is required"),
     *     @OA\Response(response=404, description="Resource not found"),
     *     security={{"oauth2_security": {}}}
     * )
     *
     * @param \Illuminate\Http\Request $request
     * @return array
     */
    public function store(Request $request)
    {
        $user = Auth::user();
        $region = $user->region;

        if (empty($region)) {
            $regionId = $request->input('region_id');

            if ($regionId) {
                $region = Region::where('id', $regionId)->first();
            }

            if (empty($region)) {
                abort(403, 'common.no.permission');
            }
        }
        $validatedData = $request->validate([
            'name' => 'required|string|max:255',
            'therapist_limit' => 'required|integer|min:1',
            'phc_worker_limit' => 'required|integer|min:1',
        ]);

        $regionLimitation = LimitationHelper::regionLimitation($region);

        if ($validatedData['therapist_limit'] > $regionLimitation['remaining_therapist_limit']) {
            abort(422, 'error.province.therapist_limit.greater_than.region.therapist_limit');
        }

        if ($validatedData['phc_worker_limit'] > $regionLimitation['remaining_phc_worker_limit']) {
            abort(422, 'error.province.phc_worker_limit.greater_than.region.phc_worker_limit');
        }

        Province::create([
            ...$validatedData,
            'region_id' => $region->id,
        ]);

        return response()->json(['message' => 'province.success_message.add'], 201);
    }

    /**
     * @OA\Post(
     *     path="/api/provinces/{id}",
     *     tags={"Province"},
     *     summary="Update the province",
     *     operationId="updateProvince",
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"name","therapist_limit","phc_worker_limit"},
     *             @OA\Property(property="name", type="string", description="Province name"),
     *             @OA\Property(property="therapist_limit", type="integer", description="Therapist limit", minimum=1),
     *             @OA\Property(property="phc_worker_limit", type="integer", description="PHC worker limit", minimum=1)
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Successful operation",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean"),
     *             @OA\Property(property="message", type="string")
     *         )
     *     ),
     *     @OA\Response(response=400, description="Bad request"),
     *     @OA\Response(response=401, description="Authentication is required"),
     *     @OA\Response(response=404, description="Resource not found"),
     *     security={{"oauth2_security": {}}}
     * )
     *
     * @param \Illuminate\Http\Request $request
     * @param \App\Models\Province $province
     * @return array
     */
    public function update(Request $request, Province $province)
    {

        $user = Auth::user();
        $region = $user->region;

        if (empty($region)) {
            $regionId = $request->input('region_id');

            if ($regionId) {
                $region = Region::where('id', $regionId)->first();
            }

            if (empty($region)) {
                abort(403, 'common.no.permission');
            }
        }

        $validatedData = $request->validate([
            'name' => 'required|string|max:255',
            'therapist_limit' => 'required|integer|min:1',
            'phc_worker_limit' => 'required|integer|min:1',
            'region_id' => 'required|exists:regions,id',
        ]);

        $regionLimitation = LimitationHelper::regionLimitation($region);
        $provinceLimitation = LimitationHelper::provinceLimitation($province->id);

        if ($validatedData['therapist_limit'] > $regionLimitation['remaining_therapist_limit'] + $region->therapist_limit) {
            return response()->json([
                'message' => 'error.province.therapist_limit.greater_than.region.therapist_limit',
                'translate_params' => [
                    'allocated_therapist_limit' => $regionLimitation['allocated_therapist_limit'],
                    'remaining_therapist_limit' => $regionLimitation['remaining_therapist_limit'] + $region->therapist_limit,
                    'therapist_limit_used' => $regionLimitation['therapist_limit_used'] - $region->therapist_limit,
                ]
            ], 422);
        }

        if ($validatedData['phc_worker_limit'] > $regionLimitation['remaining_phc_worker_limit'] + $region->phc_worker_limit) {
            return response()->json([
                'message' => 'error.province.phc_worker_limit.greater_than.region.phc_worker_limit',
                'translate_params' => [
                    'allocated_phc_worker_limit' => $regionLimitation['allocated_phc_worker_limit'],
                    'remaining_phc_worker_limit' => $regionLimitation['remaining_phc_worker_limit'] + $region->phc_worker_limit,
                    'phc_worker_limit_used' => $regionLimitation['phc_worker_limit_used'] - $region->phc_worker_limit,
                ]
            ], 422);
        }

        if ($provinceLimitation['therapist_limit_used'] > $validatedData['therapist_limit']) {
            abort(422, 'error.province.therapist_limit.less_than.total.clinic.therapist_limit');
        }

        if ($provinceLimitation['phc_worker_limit_used'] > $validatedData['phc_worker_limit']) {
            abort(422, 'error.province.phc_worker_limit.less_than.total.clinic.phc_worker_limit');
        }

        $province->update($validatedData);

        return response()->json(['message' => 'province.success_message.update'], 200);
    }

    /**
     * @OA\Delete(
     *     path="/api/provinces/{id}",
     *     tags={"Province"},
     *     summary="Delete province",
     *     operationId="deleteProvince",
     *      @OA\Parameter(
     *         name="id",
     *         in="path",
     *         description="Province id",
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
     * @param \App\Models\Language $language
     *
     * @return array
     * @throws \Exception
     */
    public function destroy(Province $province)
    {
        $provinceId = $province->id;
        $country = $province->region->country;

        $adminUsers = User::whereHas('phcService', function ($q) use ($province) {
            $q->where('province_id', $province->id);
        })
            ->whereHas('clinic', function ($q) use ($province) {
                $q->where('province_id', $province->id);
            })
            ->get();

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


        // Therapist service
        Http::withToken(Forwarder::getAccessToken(Forwarder::THERAPIST_SERVICE))
            ->post(env('THERAPIST_SERVICE_URL') . '/data-clean-up/users/delete', [
                'entity_name' => 'province',
                'entity_id' => $provinceId,
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
                'entity_name' => 'province',
                'entity_id' => $provinceId,
            ]);

        $province->forceDelete();

        return response()->json(['message' => 'province.success_message.delete'], 200);
    }

    /**
     * Get the remaining limit for a the country.
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function limitation()
    {
        $user = Auth::user();

        $limitRegionIds = $user->regions->pluck('id')->toArray();

        if ($user->region_id) {
            $limitRegionIds[] = $user->region_id;
        }

        $limitRegionIds = array_unique($limitRegionIds);

        $provinces = Province::whereIn('region_id', $limitRegionIds)->get();

        $data = [];

        foreach ($provinces as $province) {
            $provinceLimitation = LimitationHelper::provinceLimitation($province->id);

            $data[] = [
                'id' => $province->id,
                'name' => $province->name,
                'allocated_therapist_limit' => $provinceLimitation['allocated_therapist_limit'],
                'therapist_limit_used' => $provinceLimitation['therapist_limit_used'],
                'remaining_therapist_limit' => $provinceLimitation['remaining_therapist_limit'],
                'phc_worker_limit_used' => $provinceLimitation['phc_worker_limit_used'],
                'remaining_phc_worker_limit' => $provinceLimitation['remaining_phc_worker_limit'],
            ];
        }

        return response()->json(['data' => $data], 200);
    }

    /**
     * Get the province limitation.
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function getLimitation(Province $province)
    {
        return response()->json(['data' => LimitationHelper::provinceLimitation($province->id)], 200);
    }

    /**
     * Get all provinces that belong to the authenticated user's country.
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function getProvincesByUserCountry()
    {
        $authUser = Auth::user();

        $provinces = $authUser->country->provinces;

        return response()->json(['data' => $provinces]);
    }

    /**
     * Get the counts of entities related to a specific phc service.
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function getEntitiesByPhcServiceId(Province $province)
    {
        $province->load([
            'clinics',
            'phcServices',
        ]);

        $therapistPortalAccessToken = Forwarder::getAccessToken(Forwarder::THERAPIST_SERVICE);
        $patientPortalAccessToken = Forwarder::getAccessToken(Forwarder::PATIENT_SERVICE);

        $therapistCountRes = Http::withToken($therapistPortalAccessToken)->get(
            env('THERAPIST_SERVICE_URL') . '/data-clean-up/users/count',
            [
                'entity_name' => 'province',
                'entity_id' => $province->id,
                'user_type' => User::GROUP_THERAPIST,
            ]
        )->throw();

        $province->therapist_count = $therapistCountRes->json('data', 0);

        $phcWorkerCountRes = Http::withToken($therapistPortalAccessToken)->get(
            env('THERAPIST_SERVICE_URL') . '/data-clean-up/users/count',
            [
                'entity_name' => 'province',
                'entity_id' => $province->id,
                'user_type' => User::GROUP_PHC_WORKER,
            ]
        )->throw();

        $province->phc_worker_count = $phcWorkerCountRes->json('data', 0);

        $patientCountRes = Http::withToken($patientPortalAccessToken)->get(
            env('PATIENT_SERVICE_URL') . '/data-clean-up/users/count',
            [
                'entity_name' => 'province',
                'entity_id' => $province->id,
            ]
        )->throw();

        $province->patient_count = $patientCountRes->json('data', 0);

        return response()->json([
            'data' => new EntitiesByProvinceResource($province),
        ]);
    }
}
