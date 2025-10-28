<?php

namespace App\Http\Controllers;

use App\Events\ApplyMaterialAutoTranslationEvent;
use App\Helpers\ContentHelper;
use App\Helpers\FileHelper;
use App\Http\Resources\EducationMaterialResource;
use App\Models\Category;
use App\Models\EducationMaterial;
use App\Models\EducationMaterialCategory;
use App\Models\File;
use App\Models\SystemLimit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

class EducationMaterialController extends Controller
{
    /**
     * @OA\Get(
     *     path="/api/education-material",
     *     tags={"Education Material"},
     *     summary="Lists education materials",
     *     operationId="educationMaterialList",
     *     @OA\Parameter(
     *         name="page_size",
     *         in="query",
     *         description="Limit",
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
     *
     * @return array
     */
    public function index(Request $request)
    {
        $therapistId = $request->get('therapist_id');
        $filter = json_decode($request->get('filter'), true);

        $query = EducationMaterial::select('education_materials.*')->where('education_materials.parent_id', null);

        if (!empty($filter['favorites_only'])) {
            $query->join('favorite_activities_therapists', function ($join) use ($therapistId) {
                $join->on('education_materials.id', 'favorite_activities_therapists.activity_id');
            })->where('favorite_activities_therapists.therapist_id', $therapistId)
                ->where('favorite_activities_therapists.type', 'education_materials')
                ->where('favorite_activities_therapists.is_favorite', true);
        }

        if (!empty($filter['my_contents_only'])) {
            $query->where('education_materials.therapist_id', $therapistId);
        }

        if (!empty($filter['suggestions'])) {
            $query->whereHas('children');
        }

        $query->where(function ($query) use ($therapistId) {
            $query->whereNull('education_materials.therapist_id');
            if ($therapistId) {
                $query->orWhere('education_materials.therapist_id', $therapistId);
            }
        });

        if (!empty($filter['search_value'])) {
            $locale = App::getLocale();
            $query->whereRaw("JSON_EXTRACT(LOWER(title), \"$.$locale\") LIKE ?", ['%' . strtolower($filter['search_value']) . '%']);
        }

        if ($request->get('categories')) {
            $categories = $request->get('categories');
            foreach ($categories as $category) {
                $query->whereHas('categories', function ($query) use ($category) {
                    $query->where('categories.id', $category);
                });
            }
        }

        $educationMaterials = $query->paginate($request->get('page_size'));

        $info = [
            'current_page' => $educationMaterials->currentPage(),
            'total_count' => $educationMaterials->total(),
        ];
        return [
            'success' => true,
            'data' => EducationMaterialResource::collection($educationMaterials),
            'info' => $info,
        ];
    }

    /**
     * @OA\Post(
     *     path="/api/education-material",
     *     tags={"Education Material"},
     *     summary="Create education materials",
     *     operationId="createEducationMaterial",
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
     *         name="categories",
     *         in="query",
     *         description="Category id",
     *         required=false,
     *         @OA\Schema(
     *             type="string"
     *         )
     *     ),
     *     @OA\RequestBody(
     *         required=true,
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
        $therapistId = $request->get('therapist_id');
        if (!Auth::user() && !$therapistId) {
            return ['success' => false, 'message' => 'error_message.education_material_create'];
        }

        $contentLimit = ContentHelper::getContentLimitLibray(SystemLimit::THERAPIST_CONTENT_LIMIT);
        if ($therapistId) {
            $ownContentCount = ExerciseController::countTherapistLibrary($request);

            if ($ownContentCount && $ownContentCount['data'] >= $contentLimit) {
                return ['success' => false, 'message' => 'error_message.content_create.full_limit'];
            }
        }

        $uploadedFile = $request->file('file');
        if ($uploadedFile) {
            $file = FileHelper::createFile($uploadedFile, File::EDUCATION_MATERIAL_PATH, File::EDUCATION_MATERIAL_THUMBNAIL_PATH);
        }

        $copyId = $request->get('copy_id');
        if ($copyId) {
            // Clone education material.
            $educationMaterial = EducationMaterial::findOrFail($copyId)->replicate();

            // Append (copy) label to all title translations.
            $titleTranslations = $educationMaterial->getTranslations('title');
            $appendedTitles = array_map(function ($value) {
                // TODO: translate copy label to each language.
                return "$value (Copy)";
            }, $titleTranslations);
            $educationMaterial->setTranslations('title', $appendedTitles);
            $educationMaterial->save();

            // CLone files.
            if (empty($file)) {
                $originalFile = File::findOrFail($educationMaterial->file_id);
                $file = FileHelper::replicateFile($originalFile);
            }

            // Update form elements.
            $educationMaterial->update([
                'title' => $request->get('title'),
                'share_to_hi_library' => false,
                'file_id' => $file->id,
                'therapist_id' => $therapistId,
                'global' => env('APP_NAME') == 'hi',
                'share_with_phc_worker' => false,
            ]);
        } elseif (!empty($file)) {
            $educationMaterial = EducationMaterial::create([
                'title' => $request->get('title'),
                'share_to_hi_library' => $request->boolean('share_to_hi_library'),
                'file_id' => $file->id,
                'therapist_id' => $therapistId,
                'global' => env('APP_NAME') == 'hi',
                'share_with_phc_worker' => $request->boolean('share_with_phc_worker'),
            ]);
        }

        if (empty($educationMaterial)) {
            return ['success' => false, 'message' => 'error_message.education_material_create'];
        }

        // Attach category to education material.
        $this->attachCategories($educationMaterial, $request->get('categories'));

        // Add automatic translation for Education material.
        try {
            event(new ApplyMaterialAutoTranslationEvent($educationMaterial));
        } catch (\Exception $e) {
            Log::warning("Translation failed: " . $e->getMessage());
        }

        return ['success' => true, 'message' => 'success_message.education_material_create'];
    }

    /**
     * @param \Illuminate\Http\Request $request
     *
     * @return array
     */
    public function suggest(Request $request)
    {
        $therapistId = $request->get('therapist_id');
        if (!Auth::user() && !$therapistId) {
            return ['success' => false, 'message' => 'error_message.education_material_suggest'];
        }

        $foundEducationMaterial = EducationMaterial::find($request->get('id'));

        if (!$foundEducationMaterial || !$foundEducationMaterial->auto_translated || (int) $foundEducationMaterial->therapist_id === (int) $therapistId) {
            return ['success' => false, 'message' => 'error_message.education_material_suggest'];
        }

        $educationMaterial = EducationMaterial::create([
            'title' => $request->get('title'),
            'file_id' => $request->get('file_id'),
            'therapist_id' => $therapistId,
            'parent_id' => $foundEducationMaterial->id,
            'global' => env('APP_NAME') == 'hi',
            'suggested_lang' => App::getLocale(),
        ]);

        if (empty($educationMaterial)) {
            return ['success' => false, 'message' => 'error_message.education_material_suggest'];
        }
        return ['success' => true, 'message' => 'success_message.education_material_suggest'];
    }

    /**
     * @param \Illuminate\Http\Request $request
     * @param \App\Models\EducationMaterial $educationMaterial
     *
     * @return array
     */
    public function approveTranslation(Request $request, EducationMaterial $educationMaterial)
    {
        $parentEducationMaterial = EducationMaterial::find($educationMaterial->parent_id);

        if (!$parentEducationMaterial) {
            return ['success' => false, 'message' => 'error_message.education_material_update'];
        }

        $parentEducationMaterial->update([
            'title' => $request->get('title'),
            'auto_translated' => false,
        ]);

        // Remove submitted translation remaining.
        EducationMaterial::where('suggested_lang', App::getLocale())
            ->where('parent_id', $educationMaterial->parent_id)
            ->delete();

        return ['success' => true, 'message' => 'success_message.education_material_update'];
    }

    /**
     * @param \App\Models\EducationMaterial $educationMaterial
     *
     * @return \App\Http\Resources\EducationMaterialResource
     */
    public function show(EducationMaterial $educationMaterial)
    {
        return new EducationMaterialResource($educationMaterial);
    }

    /**
     * @OA\Put(
     *     path="/api/education-material/{id}",
     *     tags={"Education Material"},
     *     summary="Update education materials",
     *     operationId="updateEducationMaterial",
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         description="Material id",
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
     *     @OA\Parameter(
     *         name="lang",
     *         in="path",
     *         description="Language id",
     *         required=false,
     *         @OA\Schema(
     *             type="integer"
     *         )
     *     ),
     *     @OA\Parameter(
     *         name="categories",
     *         in="query",
     *         description="Category id",
     *         required=false,
     *         @OA\Schema(
     *          type="string"
     *         )
     *     ),
     *     @OA\RequestBody(
     *         required=true,
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
     * @param \App\Models\EducationMaterial $educationMaterial
     *
     * @return array
     */
    public function update(Request $request, EducationMaterial $educationMaterial)
    {
        $therapistId = $request->get('therapist_id');
        if (!Auth::user() && !$therapistId) {
            return ['success' => false, 'message' => 'error_message.education_material_update'];
        }

        if ((int) $educationMaterial->therapist_id !== (int) $therapistId) {
            return ['success' => false, 'message' => 'error_message.education_material_update'];
        }

        $uploadedFile = $request->file('file');
        if ($uploadedFile) {
            $oldFile = File::find($educationMaterial->file_id_no_fallback);
            if ($oldFile) {
                $oldFile->delete();
            }

            $newFile = FileHelper::createFile($uploadedFile, File::EDUCATION_MATERIAL_PATH, File::EDUCATION_MATERIAL_THUMBNAIL_PATH);
            $educationMaterial->update([
                'title' => $request->get('title'),
                'share_to_hi_library' => $request->boolean('share_to_hi_library'),
                'file_id' => $newFile->id,
                'share_with_phc_worker' => $request->boolean('share_with_phc_worker'),
            ]);
        } else {
            $educationMaterial->update([
                'title' => $request->get('title'),
                'share_to_hi_library' => $request->boolean('share_to_hi_library'),
                'share_with_phc_worker' => $request->boolean('share_with_phc_worker'),
            ]);
        }

        // Attach category to education material.
        EducationMaterialCategory::where('education_material_id', $educationMaterial->id)->delete();
        $this->attachCategories($educationMaterial, $request->get('categories'));

        return ['success' => true, 'message' => 'success_message.education_material_update'];
    }

    /**
     * @OA\Delete(
     *     path="/api/education-material/{id}",
     *     tags={"Education Material"},
     *     summary="Delete education materials",
     *     operationId="deleteEducationMaterial",
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         description="Material id",
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
     * @param \App\Models\EducationMaterial $educationMaterial
     *
     * @return array
     * @throws \Exception
     */
    public function destroy(EducationMaterial $educationMaterial)
    {
        $educationMaterial->delete();
        return ['success' => true, 'message' => 'success_message.education_material_delete'];
    }

    /**
     * @OA\Get (
     *     path="/api/education-material/list/by-ids",
     *     tags={"Education Material"},
     *     summary="Education material list",
     *     operationId="listEducationMaterialByIds",
     *     @OA\Parameter(
     *         name="material_ids[]",
     *         in="query",
     *         description="Material id",
     *         required=true,
     *          @OA\Schema(
     *              type="array",
     *              @OA\Items( type="integer"),
     *          ),
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
     * @param \Illuminate\Http\Request $request
     *
     * @return \Illuminate\Http\Resources\Json\AnonymousResourceCollection
     */
    public function getByIds(Request $request)
    {
        $materialIds = $request->get('material_ids', []);
        $materials = EducationMaterial::withTrashed()->whereIn('id', $materialIds)->get();
        return EducationMaterialResource::collection($materials);
    }

    /**
     * @OA\Post (
     *     path="/api/education-material/updateFavorite/by-therapist/{educationMaterial}",
     *     tags={"Education Material"},
     *     summary="Update favorite material",
     *     operationId="updateFavoriteMaterial",
     *     @OA\Parameter(
     *         name="educationMaterial",
     *         in="path",
     *         description="Material id",
     *         required=true,
     *          @OA\Schema(
     *              type="integer"
     *          ),
     *     ),
     *     @OA\Parameter(
     *         name="is_favorite",
     *         in="query",
     *         description="Is favorite exercise",
     *         required=true,
     *          @OA\Schema(
     *              type="integer",
     *              enum={0,1}
     *          ),
     *     ),
     *     @OA\Parameter(
     *         name="therapist_id",
     *         in="query",
     *         description="Therapist id",
     *         required=true,
     *          @OA\Schema(
     *              type="integer"
     *          ),
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
     * @param \Illuminate\Http\Request $request
     * @param \App\Models\EducationMaterial $educationMaterial
     *
     * @return array
     */
    public function updateFavorite(Request $request, EducationMaterial $educationMaterial)
    {
        $favorite = $request->get('is_favorite');
        $therapistId = $request->get('therapist_id');

        ContentHelper::flagFavoriteActivity($favorite, $therapistId, $educationMaterial);
        return ['success' => true, 'message' => 'success_message.education_material_update'];
    }

    /**
     * @param EducationMaterial $educationMaterial
     * @param string $requestCategories
     *
     * @return void
     */
    private function attachCategories($educationMaterial, $requestCategories)
    {
        $categories = $requestCategories ? explode(',', $requestCategories) : [];
        foreach ($categories as $category) {
            $educationMaterial->categories()->attach($category);
        }
    }

    /**
     * @return \App\Models\EducationMaterial[]|\Illuminate\Database\Eloquent\Collection
     */
    public function getEducationMaterials()
    {
        return EducationMaterial::withTrashed()->get();
    }

    /**
     * @return \App\Models\EducationMaterial[]|\Illuminate\Database\Eloquent\Collection
     */
    public function getEducationMaterialsForOpenLibrary()
    {
        $query = EducationMaterial::withTrashed()
            ->where('global', true)
            ->where('share_to_hi_library', true);

        return $query->get();
    }

    /**
     * @param \Illuminate\Http\Request $request
     *
     * @return \App\Models\EducationMaterialCategory[]|\Illuminate\Database\Eloquent\Collection
     */
    public function getEducationMaterialCategoriesForOpenLibrary(Request $request)
    {
        return EducationMaterialCategory::where('education_material_id', $request->get('id'))->get();
    }

    /**
     * @param \Illuminate\Http\Request $request
     *
     * @return mixed
     */
    public function getEducationMaterialFiles(Request $request)
    {
        $fileIDs = $request->get('file_ids', []);
        $files = File::whereIn('id', $fileIDs)->get();
        return $files;
    }
}
