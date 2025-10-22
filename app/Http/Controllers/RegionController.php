<?php

namespace App\Http\Controllers;

use Exception;
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
     *     )
     * )
     */
    public function index()
    {
        $regions = Auth::user()->country->regions;

        return response()->json(['data' => RegionResource::collection($regions)]);
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
     *             @OA\Property(property="name", type="string", description="Region name"),
     *             @OA\Property(property="therapist_limit", type="integer", description="Therapist limit", minimum=0),
     *             @OA\Property(property="phc_worker_limit", type="integer", description="PHC worker limit", minimum=0)
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
     * @OA\Post(
     *     path="/api/regions/{id}",
     *     tags={"Region"},
     *     summary="Update the region",
     *     operationId="updateRegion",
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"name","therapist_limit","phc_worker_limit"},
     *             @OA\Property(property="name", type="string", description="Region name"),
     *             @OA\Property(property="therapist_limit", type="integer", description="Therapist limit", minimum=0),
     *             @OA\Property(property="phc_worker_limit", type="integer", description="PHC worker limit", minimum=0)
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
     *      @OA\Parameter(
     *         name="id",
     *         in="path",
     *         description="Region id",
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
    public function destroy(Region $region)
    {
        $region->delete();

        return response()->json(['message' => 'region.success_message.delete'], 200);
    }
}
