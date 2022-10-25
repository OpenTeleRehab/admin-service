<?php

namespace App\Http\Controllers;

use App\Events\ApplyExerciseAutoTranslationEvent;
use App\Exports\ExercisesExport;
use App\Helpers\ContentHelper;
use App\Helpers\ExerciseHelper;
use App\Helpers\FileHelper;
use App\Helpers\GoogleTranslateHelper;
use App\Http\Resources\ExerciseResource;
use App\Models\AdditionalField;
use App\Models\Category;
use App\Models\Exercise;
use App\Models\ExerciseCategory;
use App\Models\File;
use App\Models\Forwarder;
use App\Models\Language;
use App\Models\SystemLimit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Maatwebsite\Excel\Facades\Excel;

class ExerciseController extends Controller
{
    /**
     * @OA\Get(
     *     path="/api/exercise",
     *     tags={"Exercise"},
     *     summary="Lists exercise",
     *     operationId="exerciseList",
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
        $query = ExerciseHelper::generateFilterQuery($request);
        $exercises = $query->paginate($request->get('page_size'));

        $info = [
            'current_page' => $exercises->currentPage(),
            'total_count' => $exercises->total(),
        ];
        return [
            'success' => true,
            'data' => ExerciseResource::collection($exercises),
            'info' => $info,
        ];
    }

    /**
     * @param \Illuminate\Http\Request $request
     *
     * @return array
     */
    public function store(Request $request)
    {
        $therapistId = $request->get('therapist_id');

        if (!Auth::user() && !$therapistId) {
            return ['success' => false, 'message' => 'error_message.exercise_create'];
        }

        $contentLimit = ContentHelper::getContentLimitLibray(SystemLimit::THERAPIST_CONTENT_LIMIT);

        if ($therapistId) {
            $ownContentCount = $this->countTherapistLibrary($request);

            if ($ownContentCount && $ownContentCount['data'] >= $contentLimit) {
                return ['success' => false, 'message' => 'error_message.content_create.full_limit'];
            }
        }

        $copyId = $request->get('copy_id');

        if ($copyId) {
            // Clone exercise.
            $exercise = Exercise::findOrFail($copyId)->replicate();

            // Append (copy) label to all title translations.
            $titleTranslations = $exercise->getTranslations('title');
            $appendedTitles = array_map(function ($value) {
                // TODO: translate copy label to each language.
                return "$value (Copy)";
            }, $titleTranslations);
            $exercise->setTranslations('title', $appendedTitles);
            $exercise->save();

            // Update form elements.
            $exercise->update([
                'title' => $request->get('title'),
                'sets' => $request->get('sets'),
                'reps' => $request->get('reps'),
                'include_feedback' => $request->boolean('include_feedback'),
                'get_pain_level' => $request->boolean('get_pain_level'),
                'therapist_id' => $therapistId,
                'global' => env('APP_NAME') == 'hi',
            ]);

            // CLone files.
            $mediaFileIDs = $request->get('media_files', []);
            foreach ($mediaFileIDs as $index => $mediaFileID) {
                $originalFile = File::findOrFail($mediaFileID);
                $file = FileHelper::replicateFile($originalFile);
                $exercise->files()->attach($file->id, ['order' => (int) $index]);
            }
        } else {
            $exercise = Exercise::create([
                'title' => $request->get('title'),
                'sets' => $request->get('sets'),
                'reps' => $request->get('reps'),
                'include_feedback' => $request->boolean('include_feedback'),
                'get_pain_level' => $request->boolean('get_pain_level'),
                'therapist_id' => $therapistId,
                'global' => env('APP_NAME') == 'hi',
            ]);
        }

        if (empty($exercise)) {
            return ['success' => false, 'message' => 'error_message.exercise_create'];
        }

        $additionalFields = json_decode($request->get('additional_fields'));
        foreach ($additionalFields as $index => $additionalField) {
            AdditionalField::create([
                'field' => $additionalField->field,
                'value' => $additionalField->value,
                'exercise_id' => $exercise->id
            ]);
        }

        // Upload files and attach to Exercise.
        $this->attachFiles($exercise, $request->allFiles());

        // Attach category to exercise.
        $this->attachCategories($exercise, $request->get('categories'));

        // Add automatic translation for Exercise.
        event(new ApplyExerciseAutoTranslationEvent($exercise));

        return ['success' => true, 'message' => 'success_message.exercise_create'];
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
            return ['success' => false, 'message' => 'error_message.exercise_suggest'];
        }

        $foundExercise = Exercise::find($request->get('id'));

        if (!$foundExercise || !$foundExercise->auto_translated || (int) $foundExercise->therapist_id === (int) $therapistId) {
            return ['success' => false, 'message' => 'error_message.exercise_suggest'];
        }

        $exercise = Exercise::create([
            'title' => $request->get('title'),
            'therapist_id' => $therapistId,
            'parent_id' => $foundExercise->id,
            'global' => env('APP_NAME') == 'hi',
            'suggested_lang' => App::getLocale(),
        ]);

        if (empty($exercise)) {
            return ['success' => false, 'message' => 'error_message.exercise_suggest'];
        }

        $additionalFields = json_decode($request->get('additional_fields'));
        foreach ($additionalFields as $index => $additionalField) {
            AdditionalField::create([
                'field' => $additionalField->field,
                'value' => $additionalField->value,
                'exercise_id' => $exercise->id,
                'parent_id' => $additionalField->id,
                'suggested_lang' => App::getLocale(),
            ]);
        }

        return ['success' => true, 'message' => 'success_message.exercise_suggest'];
    }

    /**
     * @param \App\Models\Exercise $exercise
     *
     * @return \App\Http\Resources\ExerciseResource
     */
    public function show(Exercise $exercise)
    {
        return new ExerciseResource($exercise);
    }

    /**
     * @param \Illuminate\Http\Request $request
     * @param \App\Models\Exercise $exercise
     *
     * @return array
     */
    public function update(Request $request, Exercise $exercise)
    {
        $therapistId = $request->get('therapist_id');

        if (!Auth::user() && !$therapistId) {
            return ['success' => false, 'message' => 'error_message.exercise_update'];
        }

        if ((int) $exercise->therapist_id !== (int) $therapistId) {
            return ['success' => false, 'message' => 'error_message.exercise_update'];
        }

        $exercise->update([
            'title' => $request->get('title'),
            'sets' => $request->get('sets'),
            'reps' => $request->get('reps'),
            'include_feedback' => $request->boolean('include_feedback'),
            'get_pain_level' => $request->boolean('get_pain_level'),
        ]);

        $additionalFields = json_decode($request->get('additional_fields'));
        $additionalFieldIds = [];
        $languages = Language::where('code', '<>', config('app.fallback_locale'))->get();
        $translate = new GoogleTranslateHelper();

        foreach ($additionalFields as $index => $additionalField) {
            $additionalField = AdditionalField::updateOrCreate(
                [
                    'id' => isset($additionalField->id) ? $additionalField->id : null,
                ],
                [
                    'field' => $additionalField->field,
                    'value' => $additionalField->value,
                    'exercise_id' => $exercise->id
                ]
            );
            $additionalFieldIds[] = $additionalField->id;
            if ($additionalField->wasRecentlyCreated) {
                foreach ($languages as $language) {
                    $languageCode = $language->code;
                    $translatedField = $translate->translate($additionalField->field, $languageCode);
                    $translatedValue = $translate->translate($additionalField->value, $languageCode);
                    $additionalField->setTranslation('field', $languageCode, $translatedField);
                    $additionalField->setTranslation('value', $languageCode, $translatedValue);
                    $additionalField->setTranslation('auto_translated', $languageCode, true);
                    $additionalField->save();
                }
            }
        }

        // Remove deleted additional field.
        AdditionalField::where('exercise_id', $exercise->id)
            ->whereNotIn('id', $additionalFieldIds)
            ->delete();

        // Remove files.
        $exerciseFileIDs = $exercise->files()->pluck('id')->toArray();
        $mediaFiles = $request->get('media_files', []);
        $mediaFileIDs = explode(',', $mediaFiles);
        $removeFileIDs = array_diff($exerciseFileIDs, $mediaFileIDs);

        foreach ($removeFileIDs as $removeFileID) {
            $removeFile = File::find($removeFileID);
            $removeFile->delete();
        }

        // Update ordering.
        foreach ($mediaFileIDs as $index => $mediaFileID) {
            DB::table('exercise_file')
                ->where('exercise_id', $exercise->id)
                ->where('file_id', $mediaFileID)
                ->update(['order' => $index]);
        }

        // Upload files and attach to Exercise.
        $this->attachFiles($exercise, $request->allFiles());

        // Attach category to exercise.
        ExerciseCategory::where('exercise_id', $exercise->id)->delete();
        $this->attachCategories($exercise, $request->get('categories'));

        return ['success' => true, 'message' => 'success_message.exercise_update'];
    }

    /**
     * @param \Illuminate\Http\Request $request
     * @param \App\Models\Exercise $exercise
     *
     * @return array
     */
    public function approveTranslation(Request $request, Exercise $exercise)
    {
        $parentExercise = Exercise::find($exercise->parent_id);

        if (!$parentExercise) {
            return ['success' => false, 'message' => 'error_message.exercise_update'];
        }

        $parentExercise->update([
            'title' => $request->get('title'),
            'auto_translated' => false,
        ]);

        $additionalFields = json_decode($request->get('additional_fields'));
        foreach ($additionalFields as $index => $additionalField) {
            $foundAdditionalField = AdditionalField::find($additionalField->parent_id);
            if ($foundAdditionalField) {
                $foundAdditionalField->update([
                    'field' => $additionalField->field,
                    'value' => $additionalField->value,
                    'auto_translated' => false
                ]);
            }
        }

        // Remove submitted translation remaining.
        Exercise::where('suggested_lang', App::getLocale())
            ->where('parent_id', $exercise->parent_id)
            ->delete();

        return ['success' => true, 'message' => 'success_message.exercise_update'];
    }

    /**
     * @OA\Get(
     *     path="/api/library/count/by-therapist",
     *     tags={"Exercise"},
     *     summary="Count therapist library",
     *     operationId="therapistLibraryCount",
     *     @OA\Parameter(
     *         name="therapist_id",
     *         in="query",
     *         description="Therapist id",
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
    public static function countTherapistLibrary(Request $request)
    {
        $therapistId = $request->get('therapist_id');
        $treatmentPresets = 0;
        $response = Http::withToken(Forwarder::getAccessToken(Forwarder::THERAPIST_SERVICE))
            ->get(env('THERAPIST_SERVICE_URL') . '/treatment-plan/count/by-therapist?therapist_id=' . $therapistId);

        if (!empty($response) && $response->successful()) {
            $treatmentPresets = $response->json();
        }

        $totalLibries = ContentHelper::countTherapistContents($therapistId);
        $totalLibries += $treatmentPresets;

        return [
            'success' => true,
            'data' => $totalLibries
        ];
    }

    /**
     * @OA\Post (
     *     path="/api/library/delete/by-therapist",
     *     tags={"Exercise"},
     *     summary="Library delete by therapist",
     *     operationId="deleteLibraryByTherapist",
     *     @OA\Parameter(
     *         name="therapist_id",
     *         in="query",
     *         description="Therapist id",
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
    public static function deleteLibraryByTherapist(Request $request)
    {
        $therapistId = $request->get('therapist_id');
        $country = $request->get('country');

        ContentHelper::deleteTherapistContents($therapistId);
    }

    /**
     * @OA\Delete (
     *     path="/api/exercise/{id}",
     *     tags={"Exercise"},
     *     summary="Delete exercise",
     *     operationId="deleteExercise",
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         description="Exercise id",
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
     * @param \App\Models\Exercise $exercise
     *
     * @return array
     * @throws \Exception
     */
    public function destroy(Exercise $exercise)
    {
        $exercise->delete();
        return ['success' => true, 'message' => 'success_message.exercise_delete'];
    }

    /**
     * @OA\Get (
     *     path="/api/exercise/list/by-ids",
     *     tags={"Exercise"},
     *     summary="Exercise list",
     *     operationId="listExerciseByIds",
     *     @OA\Parameter(
     *         name="exercise_ids[]",
     *         in="query",
     *         description="Exercise id",
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
        $exerciseIds = $request->get('exercise_ids', []);
        $exercises = Exercise::withTrashed()->whereIn('id', $exerciseIds)->get();
        return ExerciseResource::collection($exercises);
    }

    /**
     * @OA\Post (
     *     path="/api/exercise/updateFavorite/by-therapist/{exercise}",
     *     tags={"Exercise"},
     *     summary="Update favorite exercise",
     *     operationId="updateFavoriteExercise",
     *     @OA\Parameter(
     *         name="exercise",
     *         in="path",
     *         description="Exercise id",
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
     * @param \App\Models\Exercise $exercise
     *
     * @return array
     */
    public function updateFavorite(Request $request, Exercise $exercise)
    {
        $favorite = $request->get('is_favorite');
        $therapistId = $request->get('therapist_id');

        ContentHelper::flagFavoriteActivity($favorite, $therapistId, $exercise);
        return ['success' => true, 'message' => 'success_message.exercise_update'];
    }

    /**
     * @param \Illuminate\Http\Request $request
     * @param string $type
     *
     * @return \Symfony\Component\HttpFoundation\BinaryFileResponse
     */
    public function export(Request $request, $type)
    {
        return Excel::download(new ExercisesExport($request), "exercises.$type");
    }

    /**
     * @param Exercise $exercise
     * @param array $requestFiles
     *
     * @return void
     */
    private function attachFiles($exercise, $requestFiles)
    {
        foreach ($requestFiles as $index => $uploadedFile) {
            $file = FileHelper::createFile($uploadedFile, File::EXERCISE_PATH, File::EXERCISE_THUMBNAIL_PATH);

            if ($file) {
                $exercise->files()->attach($file->id, ['order' => (int) $index]);
            }
        }
    }

    /**
     * @param Exercise $exercise
     * @param string $requestCategories
     *
     * @return void
     */
    private function attachCategories($exercise, $requestCategories)
    {
        $categories = $requestCategories ? explode(',', $requestCategories) : [];
        foreach ($categories as $category) {
            $exercise->categories()->attach($category);
        }
    }

    /**
     * @return \App\Models\Exercise[]|\Illuminate\Database\Eloquent\Collection
     */
    public function getExercises()
    {
        return Exercise::withTrashed()->get();
    }

    /**
     * @return \App\Models\Exercise[]|\Illuminate\Database\Eloquent\Collection
     */
    public function getExercisesForOpenLibrary()
    {
        $categories = Category::where('hi_only', true)->get();
        $query = Exercise::withTrashed()->where('global', true);
        foreach ($categories as $category) {
            $query->whereDoesntHave('categories', function ($query) use ($category) {
                $query->where('categories.id', $category->id);
            });
        }
        return $query->get();
    }

    /**
     * @param \Illuminate\Http\Request $request
     *
     * @return \App\Models\ExerciseCategory[]|\Illuminate\Database\Eloquent\Collection
     */
    public function getExerciseCategoriesForOpenLibrary(Request $request)
    {
        return ExerciseCategory::where('exercise_id', $request->get('id'))->get();
    }

    /**
     * @param \Illuminate\Http\Request $request
     *
     * @return mixed
     */
    public function getExerciseFiles(Request $request)
    {
        $excercise = Exercise::withTrashed()->findOrFail($request->get('exercise_id'));
        return $excercise->files()->orderBy('order')->get();
    }
}
