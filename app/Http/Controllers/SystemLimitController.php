<?php

namespace App\Http\Controllers;

use App\Helpers\ContentHelper;
use App\Http\Resources\ProfessionResource;
use App\Http\Resources\SystemLimitResource;
use App\Models\Profession;
use App\Models\SystemLimit;
use Illuminate\Http\Request;

class SystemLimitController extends Controller
{
    /**
     * @OA\Get(
     *     path="/api/system-limit",
     *     tags={"System limit"},
     *     summary="Lists all system limt",
     *     operationId="systemLimitList",
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
        $staticPages = SystemLimit::all();

        return ['success' => true, 'data' => SystemLimitResource::collection($staticPages)];
    }

    /**
     * @OA\Put(
     *     path="/api/system-limit/{id}",
     *     tags={"System limit"},
     *     summary="Update system limt",
     *     operationId="updateSystemLimit",
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         description="Id",
     *         required=true,
     *         @OA\Schema(
     *             type="integer"
     *         )
     *     ),
     *     @OA\Parameter(
     *         name="value",
     *         in="query",
     *         description="Value",
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
     * @param \App\Models\SystemLimit $systemLimit
     *
     * @return array
     */
    public function update(Request $request, SystemLimit $systemLimit)
    {
        if (!is_null($request->get('value'))) {
            $systemLimit->update([
                'value' => $request->get('value')
            ]);
        }

        return ['success' => true, 'message' => 'success_message.system_limit_update'];
    }

    /**
     * @OA\Get(
     *     path="/api/setting/library-limit",
     *     tags={"System limit"},
     *     summary="Get library content limit",
     *     operationId="getLibraryContentLimit",
     *     @OA\Parameter(
     *         name="type",
     *         in="query",
     *         description="Type",
     *         required=true,
     *         @OA\Schema(
     *             type="string"
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
     * @return int
     */
    public function getContentLimitForLibrary(Request $request)
    {
        $type = $request->get('type');
        $contentLimit = ContentHelper::getContentLimitLibray($type);

        return $contentLimit;
    }

    /**
     * @OA\Get(
     *     path="/api/system-limit/list/by-type",
     *     tags={"System limit"},
     *     summary="Get system limit by type",
     *     operationId="getSystemLimitByType",
     *     @OA\Parameter(
     *         name="type",
     *         in="query",
     *         description="Type",
     *         required=true,
     *         @OA\Schema(
     *             type="string"
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
     *
     * @return array
     */
    public function getSystemLimitByType(Request $request)
    {
        $type = $request->get('type');
        $Limit = SystemLimit::where('content_type', $type)->first();

        return ['success' => true, 'data' => $Limit];
    }
}
