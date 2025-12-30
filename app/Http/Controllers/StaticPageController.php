<?php

namespace App\Http\Controllers;

use App\Events\ApplyStaticPageAutoTranslationEvent;
use App\Helpers\FileHelper;
use App\Helpers\LanguageHelper;
use App\Http\Resources\StaticPageResource;
use App\Http\Resources\StaticPageIndexResource;
use App\Models\StaticPage;
use Illuminate\Http\Request;
use App\Models\File;
use Illuminate\Support\Facades\Log;

class StaticPageController extends Controller
{
    /**
     * @OA\Get(
     *     path="/api/static-page",
     *     tags={"Static page"},
     *     summary="Lists all static pages",
     *     operationId="staticPageList",
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
        $staticPages = StaticPage::select('id', 'title', 'platform')->get();

        return ['success' => true, 'data' => StaticPageIndexResource::collection($staticPages)];
    }

    /**
     * @param \App\Models\StaticPage $staticPage
     *
     * @return \App\Http\Resources\StaticPageResource
     */
    public function show(StaticPage $staticPage)
    {
        return new StaticPageResource($staticPage);
    }

    /**
     * @OA\Post(
     *     path="/api/static-page",
     *     tags={"Static page"},
     *     summary="Create static pages",
     *     operationId="createStaticPage",
     *     @OA\Parameter(
     *         name="url",
     *         in="query",
     *         description="Url",
     *         required=true,
     *         @OA\Schema(
     *             type="string"
     *         )
     *     ),
     *     @OA\Parameter(
     *         name="platform",
     *         in="query",
     *         description="Platform",
     *         required=true,
     *         @OA\Schema(
     *             type="string"
     *         )
     *     ),
     *     @OA\RequestBody(
     *         required=false,
     *         @OA\MediaType(
     *             mediaType="multipart/form-data",
     *             @OA\Schema(
     *                 @OA\Property(
     *                     description="file to upload",
     *                     property="file",
     *                     type="file",
     *                ),
     *             )
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
     *     @OA\Parameter(
     *         name="content",
     *         in="query",
     *         description="Content",
     *         required=true,
     *         @OA\Schema(
     *             type="string"
     *         )
     *     ),
     *     @OA\Parameter(
     *         name="private",
     *         in="query",
     *         description="Private",
     *         required=false,
     *         @OA\Schema(
     *             type="boolean"
     *         )
     *     ),
     *     @OA\Parameter(
     *         name="background_color",
     *         in="query",
     *         description="Background color",
     *         required=false,
     *         @OA\Schema(
     *             type="string"
     *         )
     *     ),
     *     @OA\Parameter(
     *         name="text_color",
     *         in="query",
     *         description="Text color",
     *         required=false,
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
        $uploadedFile = $request->file('file');
        $file = null;
        if ($uploadedFile) {
            $file = FileHelper::createFile($uploadedFile, File::STATIC_PAGE_PATH);
        }

        $existingUrl = StaticPage::where('url_path_segment', $request->get('url'))
            ->where('platform', $request->get('platform'))->count();
        if ($existingUrl) {
            // Todo: message will be replaced.
            return abort(409, 'error_message.url_exists');
        }

        $staticPage = StaticPage::create([
            'title' => $request->get('title'),
            'content' => $request->get('content'),
            'private' => $request->boolean('private'),
            'platform' => $request->get('platform'),
            'url_path_segment' => $request->get('url'),
            'file_id' => $file !== null ? $file->id : $file,
            'background_color' => $request->get('background_color'),
            'text_color' => $request->get('text_color')
        ]);

        // Add automatic translation for Static page.
        try {
            event(new ApplyStaticPageAutoTranslationEvent($staticPage));
        } catch (\Exception $e) {
            Log::warning("Translation failed: " . $e->getMessage());
        }

        return ['success' => true, 'message' => 'success_message.static_page_add'];
    }

    /**
     * @OA\Put(
     *     path="/api/static-page/{id}",
     *     tags={"Static page"},
     *     summary="Update static pages",
     *     operationId="updateStaticPage",
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
     *         name="url",
     *         in="query",
     *         description="Url",
     *         required=true,
     *         @OA\Schema(
     *             type="string"
     *         )
     *     ),
     *     @OA\Parameter(
     *         name="platform",
     *         in="query",
     *         description="Platform",
     *         required=true,
     *         @OA\Schema(
     *             type="string"
     *         )
     *     ),
     *     @OA\RequestBody(
     *         required=false,
     *         @OA\MediaType(
     *             mediaType="multipart/form-data",
     *             @OA\Schema(
     *                 @OA\Property(
     *                     description="file to upload",
     *                     property="file",
     *                     type="file",
     *                ),
     *             )
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
     *     @OA\Parameter(
     *         name="content",
     *         in="query",
     *         description="Content",
     *         required=true,
     *         @OA\Schema(
     *             type="string"
     *         )
     *     ),
     *     @OA\Parameter(
     *         name="private",
     *         in="query",
     *         description="Private",
     *         required=false,
     *         @OA\Schema(
     *             type="boolean"
     *         )
     *     ),
     *     @OA\Parameter(
     *         name="background_color",
     *         in="query",
     *         description="Background color",
     *         required=false,
     *         @OA\Schema(
     *             type="string"
     *         )
     *     ),
     *     @OA\Parameter(
     *         name="text_color",
     *         in="query",
     *         description="Text color",
     *         required=false,
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
     * @param \App\Models\StaticPage $staticPage
     *
     * @return array
     */
    public function update(Request $request, StaticPage $staticPage)
    {
        LanguageHelper::validateAssignedLanguage($request->get('lang'));

        $uploadedFile = $request->file('file');

        if ($uploadedFile) {
            $oldFile = File::find($staticPage->file_id);
            if ($oldFile) {
                $oldFile->delete();
            }

            $newFile = FileHelper::createFile($uploadedFile, File::STATIC_PAGE_PATH);
            $staticPage->update([
                'file_id' => $newFile->id,
            ]);
        }

        if ($request->get('file') === 'undefined') {
            $oldFile = File::find($staticPage->file_id);
            if ($oldFile) {
                $oldFile->delete();
            }
        }

        $existingStaticPage = StaticPage::where('url_path_segment', $request->get('url'))
            ->where('platform', $request->get('platform'))->first();

        if ($existingStaticPage && $existingStaticPage->id !== $staticPage->id) {
            // Todo: message will be replaced.
            return abort(409, 'error_message.url_exists');
        }

        $staticPage->update([
            'title' => $request->get('title'),
            'content' => $request->get('content'),
            'private' => $request->boolean('private'),
            'platform' => $request->get('platform'),
            'url_path_segment' => $request->get('url'),
            'background_color' => $request->get('background_color'),
            'text_color' => $request->get('text_color'),
            'auto_translated' => false,
        ]);

        return ['success' => true, 'message' => 'success_message.static_file.update'];
    }

    /**
     * @OA\Get(
     *     path="/api/page/static",
     *     tags={"Static page"},
     *     summary="get static page data",
     *     operationId="getStaticPage",
     *     @OA\Parameter(
     *         name="url-segment",
     *         in="query",
     *         description="Url",
     *         required=true,
     *         @OA\Schema(
     *             type="string"
     *         )
     *     ),
     *     @OA\Parameter(
     *         name="platform",
     *         in="query",
     *         description="Platform",
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
     * @return \Illuminate\View\View
     */
    public function getStaticPage(Request $request)
    {
        $platform = $request->get('platform');
        $page = StaticPage::where('url_path_segment', $request->get('url-segment'))
            ->where('platform', $platform)
            ->firstOrFail();

        return view('templates.default', compact('page', 'platform'));
    }

    /**
     * @OA\Get(
     *     path="/api/page/static-page-data",
     *     tags={"Static page"},
     *     summary="get static page data",
     *     operationId="getStaticPageData",
     *     @OA\Parameter(
     *         name="url-segment",
     *         in="query",
     *         description="Url",
     *         required=true,
     *         @OA\Schema(
     *             type="string"
     *         )
     *     ),
     *     @OA\Parameter(
     *         name="platform",
     *         in="query",
     *         description="Platform",
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
    public function getStaticPageData(Request $request)
    {
        $page = StaticPage::where('url_path_segment', $request->get('url-segment'))
            ->where('platform', $request->get('platform'))
            ->first();
        return ['success' => true, 'data' => $page ? new StaticPageResource($page) : []];
    }
}
