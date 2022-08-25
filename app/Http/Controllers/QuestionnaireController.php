<?php

namespace App\Http\Controllers;

use App\Events\ApplyQuestionnaireAutoTranslationEvent;
use App\Helpers\CategoryHelper;
use App\Helpers\ContentHelper;
use App\Helpers\FileHelper;
use App\Http\Resources\QuestionnaireResource;
use App\Models\Answer;
use App\Models\Category;
use App\Models\File;
use App\Models\Question;
use App\Models\Questionnaire;
use App\Models\QuestionnaireCategory;
use App\Models\SystemLimit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class QuestionnaireController extends Controller
{
    /**
     * @OA\Get(
     *     path="/api/questionnaire",
     *     tags={"Questionnaire"},
     *     summary="Lists questionnaire",
     *     operationId="questionnaireList",
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

        $query = Questionnaire::select('questionnaires.*');

        if (!empty($filter['favorites_only'])) {
            $query->join('favorite_activities_therapists', function ($join) use ($therapistId) {
                $join->on('questionnaires.id', 'favorite_activities_therapists.activity_id');
            })->where('favorite_activities_therapists.therapist_id', $therapistId)
                ->where('favorite_activities_therapists.type', 'questionnaires')
                ->where('favorite_activities_therapists.is_favorite', true);
        }

        if (!empty($filter['my_contents_only'])) {
            $query->where('questionnaires.therapist_id', $therapistId);
        }

        $query->where(function ($query) use ($therapistId) {
            $query->whereNull('questionnaires.therapist_id');
            if ($therapistId) {
                $query->orWhere('questionnaires.therapist_id', $therapistId);
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

        $questionnaires = $query->paginate($request->get('page_size'));

        $info = [
            'current_page' => $questionnaires->currentPage(),
            'total_count' => $questionnaires->total(),
        ];
        return [
            'success' => true,
            'data' => QuestionnaireResource::collection($questionnaires),
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
            return ['success' => false, 'message' => 'error_message.questionnaire_create'];
        }

        $contentLimit = ContentHelper::getContentLimitLibray(SystemLimit::THERAPIST_CONTENT_LIMIT);
        if ($therapistId) {
            $ownContentCount = ExerciseController::countTherapistLibrary($request);

            if ($ownContentCount && $ownContentCount['data'] >= $contentLimit) {
                return ['success' => false, 'message' => 'error_message.content_create.full_limit'];
            }
        }

        DB::beginTransaction();
        try {
            $files = $request->allFiles();
            $data = json_decode($request->get('data'));

            if (!empty($data->copy_id)) {
                $questionnaire = Questionnaire::findOrFail($data->copy_id)->replicate(['is_used']);

                // Append (copy) label to all title translations.
                $titleTranslations = $questionnaire->getTranslations('title');
                $appendedTitles = array_map(function ($value) {
                    // TODO: translate copy label to each language.
                    return "$value (Copy)";
                }, $titleTranslations);
                $questionnaire->setTranslations('title', $appendedTitles);
                $questionnaire->save();

                // Update form elements.
                $questionnaire->update([
                    'title' => $data->title,
                    'description' => $data->description,
                    'therapist_id' => $therapistId,
                    'global' => env('APP_NAME') == 'hi',
                ]);
            } else {
                $questionnaire = Questionnaire::create([
                    'title' => $data->title,
                    'description' => $data->description,
                    'therapist_id' => $therapistId,
                    'global' => env('APP_NAME') == 'hi',
                ]);
            }

            // Attach category to questionnaire.
            $categories = $data->categories ?: [];
            foreach ($categories as $category) {
                $questionnaire->categories()->attach($category);
            }

            $questions = $data->questions;
            foreach ($questions as $index => $question) {
                $file = null;
                if (array_key_exists($index, $files)) {
                    $file = FileHelper::createFile($files[$index], File::QUESTIONNAIRE_PATH);
                } elseif (isset($question->file) && $question->file->id) {
                    // CLone files.
                    $originalFile = File::findOrFail($question->file->id);
                    $file = FileHelper::replicateFile($originalFile);
                }

                $newQuestion = Question::create([
                    'title' => $question->title,
                    'type' => $question->type,
                    'questionnaire_id' => $questionnaire->id,
                    'file_id' => $file ? $file->id : null,
                    'order' => $index,
                ]);

                if (isset($question->answers)) {
                    foreach ($question->answers as $answer) {
                        Answer::create([
                            'description' => $answer->description,
                            'question_id' => $newQuestion->id,
                        ]);
                    }
                }
            }

            DB::commit();

            // Add automatic translation for Exercise.
            event(new ApplyQuestionnaireAutoTranslationEvent($questionnaire));

            return ['success' => true, 'message' => 'success_message.questionnaire_create'];
        } catch (\Exception $e) {
            DB::rollBack();
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }

    /**
     * @param \App\Models\Questionnaire $questionnaire
     *
     * @return \App\Http\Resources\EducationMaterialResource
     */
    public function show(Questionnaire $questionnaire)
    {
        return new QuestionnaireResource($questionnaire);
    }

    /**
     * @OA\Delete(
     *     path="/api/questionnaire/{id}",
     *     tags={"Questionnaire"},
     *     summary="Create questionnaire",
     *     operationId="createQuestionnaire",
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         description="Id",
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
     * @param \App\Models\Questionnaire $questionnaire
     *
     * @return array
     * @throws \Exception
     */
    public function destroy(Questionnaire $questionnaire)
    {
        $questionnaire->delete();

        return ['success' => true, 'message' => 'success_message.questionnaire_delete'];
    }

    /**
     * @param \Illuminate\Http\Request $request
     * @param \App\Models\Questionnaire $questionnaire
     *
     * @return array
     */
    public function update(Request $request, Questionnaire $questionnaire)
    {
        $therapistId = $request->get('therapist_id');
        if (!Auth::user() && !$therapistId) {
            return ['success' => false, 'message' => 'error_message.questionnaire_update'];
        }

        if ((int) $questionnaire->therapist_id !== (int) $therapistId) {
            return ['success' => false, 'message' => 'error_message.questionnaire_update'];
        }

        DB::beginTransaction();
        try {
            $files = $request->allFiles();
            $data = json_decode($request->get('data'));
            $noChangedFiles = $request->get('no_changed_files', []);
            $questionnaire->update([
                'title' => $data->title,
                'description' => $data->description
            ]);

            // Attach category to exercise.
            $categories = $data->categories ?: [];
            QuestionnaireCategory::where('questionnaire_id', $questionnaire->id)->delete();
            foreach ($categories as $category) {
                $questionnaire->categories()->attach($category);
            }

            $questions = $data->questions;
            $questionIds = [];

            foreach ($questions as $index => $question) {
                $questionObj = Question::updateOrCreate(
                    [
                        'id' => isset($question->id) ? $question->id : null,
                    ],
                    [
                        'title' => $question->title,
                        'type' => $question->type,
                        'questionnaire_id' => $questionnaire->id,
                        'order' => $index,
                    ]
                );

                if (!in_array($questionObj->id, $noChangedFiles)) {
                    $oldFile = File::find($questionObj->file_id);
                    if ($oldFile) {
                        $oldFile->delete();
                    }
                    if (array_key_exists($index, $files)) {
                        $file = FileHelper::createFile($files[$index], File::QUESTIONNAIRE_PATH);
                        $questionObj->update(['file_id' => $file ? $file->id : null]);
                    }
                }

                $questionIds[] = $questionObj->id;
                $answerIds = [];
                if ($question->answers) {
                    foreach ($question->answers as $answer) {
                        $answerObj = Answer::updateOrCreate(
                            [
                                'id' => isset($answer->id) ? $answer->id : null,
                            ],
                            [
                                'description' => $answer->description,
                                'question_id' => $questionObj->id,
                            ]
                        );

                        $answerIds[] = $answerObj->id;
                    }
                }

                // Remove deleted answers.
                Answer::where('question_id', $questionObj->id)
                    ->whereNotIn('id', $answerIds)
                    ->delete();
            }

            // Remove deleted questions.
            Question::where('questionnaire_id', $questionnaire->id)
                ->whereNotIn('id', $questionIds)
                ->delete();

            DB::commit();
            return ['success' => true, 'message' => 'success_message.questionnaire_update'];
        } catch (\Exception $e) {
            DB::rollBack();
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }

    /**
     * @OA\Get(
     *     path="/api/questionnaire/list/by-ids",
     *     tags={"Questionnaire"},
     *     summary="List questionnaire by ids",
     *     operationId="listQuestionnaireByIds",
     *     @OA\Parameter(
     *         name="questionnaire_ids[]",
     *         in="query",
     *         description="Questionnaire id",
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
     *
     * @param \Illuminate\Http\Request $request
     *
     * @return \Illuminate\Http\Resources\Json\AnonymousResourceCollection
     */
    public function getByIds(Request $request)
    {
        $questionnaireIds = $request->get('questionnaire_ids', []);
        $questionnaires = Questionnaire::withTrashed()->whereIn('id', $questionnaireIds)->get();
        return QuestionnaireResource::collection($questionnaires);
    }

    /**
     * @OA\Post(
     *     path="/api/questionnaire/mark-as-used/by-ids",
     *     tags={"Questionnaire"},
     *     summary="Mark questionnaire as used by ids",
     *     operationId="markQuestionnaireAsUsedByIds",
     *     @OA\Parameter(
     *         name="questionnaire_ids[]",
     *         in="query",
     *         description="Questionnaire id",
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
     *
     * @param \Illuminate\Http\Request $request
     * @return void
     */
    public function markAsUsed(Request $request)
    {
        $questionnaireIds = $request->get('questionnaire_ids', []);
        $isUsed = $request->get('is_used');
        Questionnaire::whereIn('id', $questionnaireIds)
            ->update(['is_used' => $isUsed]);
    }

    /**
     * @OA\Post (
     *     path="/api/questionnaire/updateFavorite/by-therapist/{questionnaire}",
     *     tags={"Questionnaire"},
     *     summary="Update favorite questionnaire",
     *     operationId="updateFavoriteQuestionnaire",
     *     @OA\Parameter(
     *         name="questionnaire",
     *         in="path",
     *         description="Questionnaire id",
     *         required=true,
     *          @OA\Schema(
     *              type="integer"
     *          ),
     *     ),
     *     @OA\Parameter(
     *         name="is_favorite",
     *         in="query",
     *         description="Is favorite questionnaire",
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
     *
     * @param \Illuminate\Http\Request $request
     * @param \App\Models\Questionnaire $questionnaire
     *
     * @return array
     */
    public function updateFavorite(Request $request, Questionnaire $questionnaire)
    {
        $favorite = $request->get('is_favorite');
        $therapistId = $request->get('therapist_id');

        ContentHelper::flagFavoriteActivity($favorite, $therapistId, $questionnaire);
        return ['success' => true, 'message' => 'success_message.questionnaire_update'];
    }

    /**
     * @return \App\Models\Questionnaire[]|\Illuminate\Database\Eloquent\Collection
     */
    public function getQuestionnaires()
    {
        return Questionnaire::withTrashed()->get();
    }

    /**
     * @param \Illuminate\Http\Request $request
     *
     * @return mixed
     */
    public function getQuestionnaireQuestions(Request $request)
    {
        $questionnaires = Questionnaire::withTrashed()->findOrFail($request->get('questionnaire_id'));
        return $questionnaires->questions()->get();
    }

    /**
     * @param \Illuminate\Http\Request $request
     *
     * @return mixed
     */
    public function getQuestionFile(Request $request)
    {
        $question = Question::findOrFail($request->get('question_id'));
        return $question->file()->first();
    }

    /**
     * @param \Illuminate\Http\Request $request
     *
     * @return mixed
     */
    public function getQuestionAnswers(Request $request)
    {
        $question = Question::findOrFail($request->get('question_id'));
        return $question->answers()->get();
    }
}
