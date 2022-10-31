<?php

namespace App\Http\Controllers;

use App\Events\ApplyGuidanceAutoTranslationEvent;
use App\Helpers\FileHelper;
use App\Http\Resources\GuidancePageResource;
use App\Models\Guidance;
use App\Models\StaticPage;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\Request;
use App\Models\File;

class GuidancePageController extends Controller
{
    /**
     * @OA\Get(
     *     path="/api/guidance-page",
     *     tags={"Guidance Page"},
     *     summary="Lists all pages",
     *     operationId="guidancePageList",
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
        $guidancePages = Guidance::all();

        return ['success' => true, 'data' => GuidancePageResource::collection($guidancePages)];
    }

    /**
     * @OA\Post(
     *     path="/api/guidance-page",
     *     tags={"Guidance Page"},
     *     summary="Create guidance page",
     *     operationId="createGuidancePage",
     *     @OA\Parameter(
     *         name="title",
     *         in="query",
     *         description="Title",
     *         required=true,
     *         @OA\Schema(
     *             type="string"
     *         )
     *     ),
     *      @OA\Parameter(
     *         name="content",
     *         in="query",
     *         description="Content",
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
     * @param \Illuminate\Http\Request $request
     *
     * @return array
     */
    public function store(Request $request)
    {
        $lastOrderingIndex = Guidance::all()->count() + 1;

        $guidance = Guidance::create([
            'title' => $request->get('title'),
            'content' => $request->get('content'),
            'order' => $lastOrderingIndex
        ]);

        // Add automatic translation for Guidance.
        event(new ApplyGuidanceAutoTranslationEvent($guidance));

        return ['success' => true, 'message' => 'success_message.guidance_add'];
    }

    /**
     * @param \App\Models\Guidance $guidancePage
     *
     * @return \App\Http\Resources\GuidancePageResource
     */
    public function show(Guidance $guidancePage)
    {
        return new GuidancePageResource($guidancePage);
    }

    /**
     * @OA\Put(
     *     path="/api/guidance-page/{id}",
     *     tags={"Guidance Page"},
     *     summary="Update guidance page",
     *     operationId="updateGuidancePage",
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
     *         name="title",
     *         in="query",
     *         description="Title",
     *         required=true,
     *         @OA\Schema(
     *             type="string"
     *         )
     *     ),
     *      @OA\Parameter(
     *         name="content",
     *         in="query",
     *         description="Content",
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
     * @param \Illuminate\Http\Request $request
     * @param \App\Models\Guidance $guidancePage
     *
     * @return array
     */
    public function update(Request $request, Guidance $guidancePage)
    {
        $guidancePage->update([
            'title' => $request->get('title'),
            'content' => $request->get('content'),
            'auto_translated' => false,
        ]);

        return ['success' => true, 'message' => 'success_message.guidance.update'];
    }

    /**
     * @param \Illuminate\Http\Request $request
     *
     * @return array
     */
    public function updateOrder(Request $request)
    {
        $data = json_decode($request->get('data'));
        $guidancePages = $data->guidancePages;
        foreach ($guidancePages as $index => $guidencePage) {
            $guidancePage = Guidance::updateOrCreate(
                [
                    'id' => isset($guidencePage->id) ? $guidencePage->id : null,
                ],
                [
                    'order' => $index,
                ]
            );
        }

        return ['success' => true, 'message' => 'success_message.guidance.update'];
    }

    /**
     * @OA\Delete(
     *     path="/api/guidance-page/{id}",
     *     tags={"Guidance Page"},
     *     summary="Delete guidance page",
     *     operationId="deleteGuidance",
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         description="Guidance id",
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
     * @param \App\Models\Guidance $guidancePage
     *
     * @return array
     * @throws \Exception
     */
    public function destroy(Guidance $guidancePage)
    {
        if ($guidancePage->delete()) {
            return ['success' => true, 'message' => 'success_message.guidance.delete'];
        }
        return ['success' => true, 'message' => 'error_message.guidance.delete'];
    }
}
