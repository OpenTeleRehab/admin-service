<?php

namespace App\Http\Controllers;

use Exception;
use App\Models\Province;
use Illuminate\Http\Request;
use App\Helpers\LimitationHelper;
use Illuminate\Support\Facades\Auth;
use App\Http\Resources\ProvinceResource;

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
        $searchValue = $request->get('search_value');
        $pageSize = $request->get('page_size');
        $query = Auth::user()->region->provinces();
        if ($searchValue) {
            $query->where('name' ,'like', '%' . $searchValue . '%');
        }
        $provinces = $query->paginate($pageSize);
        

        return response()->json(['data' => ProvinceResource::collection($provinces), 'total' => $provinces->total(), 'current_page' => $provinces->currentPage()]);
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
        $region = Auth::user()->region;

        if (empty($region)) {
            abort(403, 'common.no.permission');
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

        Province::create($validatedData);

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
        $region = Auth::user()->region;

        if (empty($region)) {
            abort(403, 'common.no.permission');
        }

        $validatedData = $request->validate([
            'name' => 'required|string|max:255',
            'therapist_limit' => 'required|integer|min:1',
            'phc_worker_limit' => 'required|integer|min:1',
        ]);

        $regionLimitation = LimitationHelper::regionLimitation($region);

        if ($validatedData['therapist_limit'] > $regionLimitation['remaining_therapist_limit'] + $region->therapist_limit) {
            abort(422, 'error.province.therapist_limit.greater_than.region.therapist_limit');
        }

        if ($validatedData['phc_worker_limit'] > $regionLimitation['remaining_phc_worker_limit'] + $region->phc_worker_limit) {
            abort(422, 'error.province.phc_worker_limit.greater_than.region.phc_worker_limit');
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
        $province->delete();

        return response()->json(['message' => 'province.success_message.delete'], 200);
    }
}
