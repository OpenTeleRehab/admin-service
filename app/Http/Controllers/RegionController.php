<?php

namespace App\Http\Controllers;

use App\Models\Region;
use Illuminate\Http\Request;
use App\Helpers\LimitationHelper;
use Illuminate\Support\Facades\Auth;
use App\Http\Resources\RegionResource;

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
        $pageSize = $request->get('page_size');
        $query = Auth::user()->country->regions();
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
     * @return array
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

        if ($validatedData['therapist_limit'] > $countryLimitation['remaining_therapist_limit'] + $region->therapist_limit) {
            abort(422, 'error.region.therapist_limit.greater_than.country.therapist_limit');
        }

        if ($validatedData['phc_worker_limit'] > $countryLimitation['remaining_phc_worker_limit'] + $country->phc_worker_limit) {
            abort(422, 'error.region.phc_worker_limit.greater_than.country.phc_worker_limit');
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
        $region->delete();

        return response()->json(['message' => 'region.success_message.delete'], 200);
    }

    /**
     * Get the region limitation.
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function getLimitation()
    {
        $region = Auth::user()->region;

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
        $regions = $query->paginate($pageSize);

        return response()->json([
            'data' => RegionResource::collection($regions),
            'total' => $regions->total(),
            'current_page' => $regions->currentPage()
        ], 200);
    }
}
