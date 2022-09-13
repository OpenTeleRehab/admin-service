<?php

namespace App\Http\Controllers;

use App\Events\ApplyNewLanguageTranslationEvent;
use App\Http\Resources\LanguageResource;
use App\Models\Language;
use Illuminate\Http\Request;

class LanguageController extends Controller
{
    /**
     * @OA\Get(
     *     path="/api/language",
     *     tags={"Language"},
     *     summary="Lists all languages",
     *     operationId="languageList",
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
    public function index(Request $request)
    {
        $languages = Language::all();

        return ['success' => true, 'data' => LanguageResource::collection($languages)];
    }

    /**
     * @OA\Post(
     *     path="/api/language",
     *     tags={"Language"},
     *     summary="Create language",
     *     operationId="createlanguage",
     *     @OA\Parameter(
     *         name="name",
     *         in="query",
     *         description="Name",
     *         required=true,
     *         @OA\Schema(
     *             type="string"
     *         )
     *     ),
     *      @OA\Parameter(
     *         name="code",
     *         in="query",
     *         description="Code",
     *         required=true,
     *         @OA\Schema(
     *             type="string"
     *         )
     *     ),
     *      @OA\Parameter(
     *         name="rtl",
     *         in="query",
     *         description="RTL",
     *         required=false,
     *         @OA\Schema(
     *             type="boolean"
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
     * @return array|void
     */
    public function store(Request $request)
    {
        $code = $request->get('code');
        $rtl = $request->boolean('rtl');
        $availableLanguage = Language::where('code', $code)->count();
        if ($availableLanguage) {
            return abort(409, 'error_message.language_exists');
        }

        Language::create([
            'name' => $request->get('name'),
            'code' => $code,
            'rtl' => $rtl,
        ]);

        return ['success' => true, 'message' => 'success_message.language_add'];
    }

    /**
     * @OA\Put(
     *     path="/api/language/{id}",
     *     tags={"Language"},
     *     summary="Update language",
     *     operationId="updateLanguage",
     *      @OA\Parameter(
     *         name="id",
     *         in="path",
     *         description="Language id",
     *         required=true,
     *         @OA\Schema(
     *             type="integer"
     *         )
     *     ),
     *     @OA\Parameter(
     *         name="name",
     *         in="query",
     *         description="Name",
     *         required=true,
     *         @OA\Schema(
     *             type="string"
     *         )
     *     ),
     *      @OA\Parameter(
     *         name="code",
     *         in="query",
     *         description="Code",
     *         required=true,
     *         @OA\Schema(
     *             type="string"
     *         )
     *     ),
     *      @OA\Parameter(
     *         name="rtl",
     *         in="query",
     *         description="RTL",
     *         required=false,
     *         @OA\Schema(
     *             type="boolean"
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
     * @param \App\Models\Language $language
     *
     * @return array|void
     */
    public function update(Request $request, Language $language)
    {
        $code = $request->get('code');
        $rtl = $request->boolean('rtl');
        $availableLanguage = Language::where('id', '<>', $language->id)
            ->where('code', $code)
            ->count();
        if ($availableLanguage) {
            return abort(409, 'error_message.language_exists');
        }

        $language->update([
            'name' => $request->get('name'),
            'code' => $code,
            'rtl' => $rtl,
        ]);

        return ['success' => true, 'message' => 'success_message.language_update'];
    }

    /**
     * @OA\Put(
     *     path="/api/language_auto_translate/{id}",
     *     tags={"Language"},
     *     summary="Auto translate by language",
     *     operationId="autoTranslateLanguage",
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
     * @return array|void
     */
    public function autoTranslate(Language $language)
    {
        if (!$language || !$language->code) {
            return abort(500, 'error_message.language_exists');
        }

        event(new ApplyNewLanguageTranslationEvent($language->code));
        $language->update(['auto_translated' => true]);

        return ['success' => true, 'message' => 'success_message.auto_translated'];
    }

    /**
     * @OA\Delete(
     *     path="/api/language/{id}",
     *     tags={"Language"},
     *     summary="Delete language",
     *     operationId="deleteLanguage",
     *      @OA\Parameter(
     *         name="id",
     *         in="path",
     *         description="Language id",
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
    public function destroy(Language $language)
    {
        if (!$language->isUsed()) {
            $language->delete();
            return ['success' => true, 'message' => 'success_message.language_delete'];
        }
        return ['success' => false, 'message' => 'error_message.language_delete'];
    }

    /**
     * @OA\Get(
     *     path="/api/language/by-id/{id}",
     *     tags={"Language"},
     *     summary="Get language by id",
     *     operationId="GetlanguageById",
     *      @OA\Parameter(
     *         name="id",
     *         in="path",
     *         description="Language id",
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
     * @param integer $id
     *
     * @return array
     */
    public function getById($id)
    {
        return ['success' => true, 'data' => Language::find($id)];
    }
}
