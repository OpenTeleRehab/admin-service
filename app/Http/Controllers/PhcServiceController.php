<?php

namespace App\Http\Controllers;

use App\Helpers\JsonColumnHelper;
use App\Helpers\KeycloakHelper;
use App\Http\Resources\PhcServiceResource;
use App\Models\PhcService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use App\Helpers\LimitationHelper;
use App\Http\Resources\EntitiesByPhcServiceResource;
use App\Models\Province;
use App\Models\Forwarder;
use App\Models\User;
use App\Models\UserSurvey;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class PhcServiceController extends Controller
{
    /**
     * @OA\Get(
     *     path="/api/phc-service",
     *     tags={"PHC Service"},
     *     summary="List of phc services",
     *     operationId="getPhcServices",
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
     *                     @OA\Property(property="province_id", type="integer", example=1),
     *                     @OA\Property(property="name", type="string", example="Province Name"),
     *                    @OA\Property(property="phone_number", type="string", example="1234567890"),
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
        $searchValue = $request->get('search_value');
        $pageSize = $request->get('page_size');

        $user = Auth::user();
        $limitRegionIds = $user->regions->pluck('id')->toArray();

        if ($user->region_id) {
            $limitRegionIds[] = $user->region_id;
        }

        $limitRegionIds = array_unique($limitRegionIds);

        $query = PhcService::whereHas('province', function ($query) use ($limitRegionIds) {
            $query->whereIn('region_id', $limitRegionIds);
        });

        if ($searchValue) {
            $query->where('phc_services.name', 'like', '%' . $searchValue . '%');
        }

        if ($request->has('filters')) {
            $filters = $request->get('filters');
            $query->where(function ($query) use ($filters) {
                foreach ($filters as $filter) {
                    $filterObj = json_decode($filter);
                    if ($filterObj->columnName === 'province') {
                        $query->where('province_id', $filterObj->value);
                    } else {
                        $query->where('phc_services.' . $filterObj->columnName, 'like', '%' .  $filterObj->value . '%');
                    }
                }
            });
        }

        $phcServices = $query->paginate($pageSize);

        return response()->json(['data' => PhcServiceResource::collection($phcServices), 'total' => $phcServices->total(), 'current_page' => $phcServices->currentPage()]);
    }

    /**
     * @OA\Post(
     *     path="/api/phc-service",
     *     tags={"PHC Service"},
     *     summary="Create a phc service",
     *     operationId="createPhcService",
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"name","province_id","phone_number","dial_code","phc_worker_limit"},
     *             @OA\Property(property="name", type="string", description="Phc service name"),
     *             @OA\Property(property="province_id", type="integer", description="Province id"),
     *             @OA\Property(property="phone_number", type="string", description="Phone number"),
     *             @OA\Property(property="dial_code", type="string", description="Dial code"),
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
        $validatedData = $request->validate([
            'name' => 'required|string|max:255',
            'province_id' => 'required|integer',
            'phone_number' => 'required|string|max:20',
            'dial_code' => 'required|string|max:10',
            'phc_worker_limit' => 'required|integer|min:1',
        ]);
        $province = Province::findOrFail($validatedData['province_id']);
        $provinceLimitation = LimitationHelper::provinceLimitation($province->id);

        if ($validatedData['phc_worker_limit'] > $provinceLimitation['remaining_phc_worker_limit']) {
            abort(422, 'error.phc_service.phc_worker_limit.greater_than.province.phc_worker_limit');
        }

        PhcService::create($validatedData);

        return response()->json(['message' => 'phc_service.success_message.add'], 201);
    }

    /**
     * @OA\Post(
     *     path="/api/phc-service/{id}",
     *     tags={"PHC Service"},
     *     summary="Update the phc service",
     *     operationId="updatePhcService",
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"name","province_id","phone_number","dial_code","phc_worker_limit"},
     *             @OA\Property(property="name", type="string", description="PHC service name"),
     *             @OA\Property(property="province_id", type="integer", description="Province id"),
     *             @OA\Property(property="phone_number", type="string", description="Phone number"),
     *             @OA\Property(property="dial_code", type="string", description="Dial code"),
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
    public function update(Request $request, PhcService $phcService)
    {
        $validatedData = $request->validate([
            'name' => 'required|string|max:255',
            'province_id' => 'required|integer',
            'phone_number' => 'required|string|max:20',
            'dial_code' => 'required|string|max:10',
            'phc_worker_limit' => 'required|integer|min:1',
        ]);

        $province = Province::findOrFail($validatedData['province_id']);
        $provinceLimitation = LimitationHelper::provinceLimitation($province->id);
        $totalPhcWorker = $this->countPhcWorker($phcService->id);

        if ($totalPhcWorker > $validatedData['phc_worker_limit']) {
            abort(422, 'error.province.phc_worker_limit.less_than.total.phc_service.phc_worker_limit');
        }

        if ($validatedData['phc_worker_limit'] > $provinceLimitation['remaining_phc_worker_limit'] + $province->phc_worker_limit) {
            return response()->json([
                'message' => 'error.phc_service.phc_worker_limit.greater_than.province.phc_worker_limit',
                'translate_params' => [
                    'allocated_phc_worker_limit' => $provinceLimitation['allocated_phc_worker_limit'],
                    'remaining_phc_worker_limit' => $provinceLimitation['remaining_phc_worker_limit'] + $province->phc_worker_limit,
                    'phc_worker_limit_used' => $provinceLimitation['phc_worker_limit_used'] - $province->phc_worker_limit,
                ]
            ], 422);
        }

        $phcService->update($validatedData);

        return response()->json(['message' => 'phc_service.success_message.update'], 200);
    }

    /**
     * @OA\Delete(
     *     path="/api/phc-service/{id}",
     *     tags={"PHC Service"},
     *     summary="Delete PHC Service",
     *     operationId="deletePhcService",
     *      @OA\Parameter(
     *         name="id",
     *         in="path",
     *         description="PHC Service id",
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
    public function destroy(PhcService $phcService)
    {
        $phcServiceId = $phcService->id;
        $country = $phcService->province->region->country;

        $adminUsers = User::where('phc_service_id', $phcServiceId)->get();

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
                'entity_name' => 'phc_service',
                'entity_id' => $phcServiceId,
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
                'entity_name' => 'phc_service',
                'entity_id' => $phcServiceId,
            ]);

        $phcService->forceDelete();

        return response()->json(['message' => 'phc_service.success_message.delete'], 200);
    }

    /**
     * @OA\Get(
     *     path="/api/phc-service/phc-worker-limit/count/by-province",
     *     tags={"PHC Service"},
     *     summary="Total PHC worker limit by province",
     *     operationId="totalPhcWorkerLimit",
     *     @OA\Parameter(
     *         name="province_id",
     *         in="query",
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
     * @param Request $request
     * @return array
     */
    public function countPhcWorkerLimitByProvince(Request $request)
    {
        $provinceId = $request->get('provinceId');
        $query = PhcService::where('province_id', $provinceId);
        if ($request->has('phcServiceId')) {
            $phcServiceId = $request->get('phcServiceId');
            $query->where('id', '!=', $phcServiceId);
        }

        $phcWorkerLimitTotal = $query->sum('phc_worker_limit');

        return [
            'success' => true,
            'data' => $phcWorkerLimitTotal
        ];
    }

    /**
     * Get PHC Services by province.
     *
     * @param \Illuminate\Http\Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function getByProvince(Request $request)
    {
        $province = Province::findOrFail($request->get('province_id'));
        $phcServices = $province->phcServices;

        return response()->json(['data' => PhcServiceResource::collection($phcServices)], 200);
    }

    /**
     * Get the PHC services associated with the user's region.
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function getOptionList()
    {
        if (Auth::user()->country_id) {
            $phcServices = PhcService::whereHas('province.region', function ($q) {
                $q->where('country_id', Auth::user()->country_id);
            })->get();
        } else {
            $phcServices = PhcService::all();
        }

        return response()->json(['data' => $phcServices], 200);
    }

    /**
     * @OA\Get(
     *     path="/api/phc-services/count-phc-worker",
     *     tags={"PHC Service"},
     *     summary="Total PHC worker by PHC service",
     *     operationId="totalPhcWorkerByPhcService",
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
    public function countPhcWorkerByPhcService()
    {
        $phcServiceId = Auth::user()->phc_service_id;

        $phcWorkerData = $this->countPhcWorker($phcServiceId);

        return [
            'success' => true,
            'data' => $phcWorkerData
        ];
    }

    private function countPhcWorker($phcServiceId)
    {
        $phcWorkerData = [];
        $response = Http::withToken(Forwarder::getAccessToken(Forwarder::THERAPIST_SERVICE))
            ->get(env('THERAPIST_SERVICE_URL') . '/phc-workers/count-by-phc-service', [
                'phc_service_id' => $phcServiceId
            ]);

        if (!empty($response) && $response->successful()) {
            $phcWorkerData = $response->json();
        }

        return $phcWorkerData['data'] ?? 0;
    }

    /**
     * Get the counts of entities related to a specific phc service.
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function getEntitiesByPhcServiceId(PhcService $phcService)
    {
        $phcService->load(['users']);

        $therapistPortalAccessToken = Forwarder::getAccessToken(Forwarder::THERAPIST_SERVICE);
        $patientPortalAccessToken = Forwarder::getAccessToken(Forwarder::PATIENT_SERVICE);

        $phcWorkerCountRes = Http::withToken($therapistPortalAccessToken)->get(
            env('THERAPIST_SERVICE_URL') . '/data-clean-up/users/count',
            [
                'entity_name' => 'phc_service',
                'entity_id' => $phcService->id,
                'user_type' => User::GROUP_PHC_WORKER,
            ]
        )->throw();

        $phcService->phc_worker_count = $phcWorkerCountRes->json('data', 0);

        $patientCountRes = Http::withToken($patientPortalAccessToken)->get(
            env('PATIENT_SERVICE_URL') . '/data-clean-up/users/count',
            [
                'entity_name' => 'phc_service',
                'entity_id' => $phcService->id,
            ]
        )->throw();

        $phcService->patient_count = $patientCountRes->json('data', 0);

        return response()->json([
            'data' => new EntitiesByPhcServiceResource($phcService),
        ]);
    }
}
