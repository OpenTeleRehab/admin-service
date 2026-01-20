<?php

namespace App\Http\Controllers;

use App\Events\ApplyGuidanceAutoTranslationEvent;
use App\Helpers\FileHelper;
use App\Helpers\LanguageHelper;
use App\Http\Resources\GuidancePageResource;
use App\Models\Guidance;
use App\Models\StaticPage;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\Request;
use App\Models\File;
use Illuminate\Support\Facades\Log;

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
    public function index(Request $request)
    {
        $validatedData = $request->validate([
            'target_role' => 'required|in:therapist,phc_worker',
        ]);

        $guidancePages = Guidance::where('target_role', $validatedData['target_role'])->get();

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
     *     @OA\Parameter(
     *         name="target_role",
     *         in="query",
     *         description="Target Role",
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
        $lastOrderingIndex = Guidance::count() + 1;

        $guidance = Guidance::create([
            'title' => $request->get('title'),
            'content' => $request->get('content'),
            'order' => $lastOrderingIndex,
            'target_role' => $request->get('target_role'),
        ]);

        // Add automatic translation for Guidance.
        try {
            event(new ApplyGuidanceAutoTranslationEvent($guidance));
        } catch (\Exception $e) {
            Log::warning("Translation failed: " . $e->getMessage());
        }

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
     *     @OA\Parameter(
     *         name="target_role",
     *         in="query",
     *         description="Target Role",
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
        LanguageHelper::validateAssignedLanguage($request->get('lang'));

        $guidancePage->update([
            'title' => $request->get('title'),
            'content' => $request->get('content'),
            'target_role' => $request->get('target_role'),
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
        foreach ($guidancePages as $index => $guidancePage) {
            Guidance::where('id', $guidancePage->id)
                ->update([
                    'order' => $index,
                ]);
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

    /**
     * Get all tutorials.
     *
     * @return \Illuminate\Database\Eloquent\Collection
     */
    public function getTutorials()
    {
        return Guidance::all();
    }

    /**
     * Get tutorial files by IDs.
     *
     * @param \Illuminate\Http\Request $request
     * @return \Illuminate\Database\Eloquent\Collection
     */
    public function getTutorialFiles(Request $request)
    {
        $fileIds = $request->get('file_ids', []);
        $files = File::whereIn('id', $fileIds)->get();

        return $files;
    }
}
