<?php

namespace App\Http\Controllers;

use App\Events\ApplyQuestionnaireAutoTranslationEvent;
use App\Helpers\ContentHelper;
use App\Helpers\FileHelper;
use App\Helpers\GoogleTranslateHelper;
use App\Http\Resources\QuestionnaireResource;
use App\Models\Answer;
use App\Models\Category;
use App\Models\File;
use App\Models\Language;
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

        $query = Questionnaire::select('questionnaires.*')->where('questionnaires.parent_id', null);

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

        if (!empty($filter['suggestions'])) {
            $query->whereHas('children');
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
                $file_index_key = array_keys($files);
                $file = null;

                if (array_key_exists($file_index_key[$index], $files)) {
                    $file = FileHelper::createFile($files[$file_index_key[$index]], File::QUESTIONNAIRE_PATH);
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
     * @param \Illuminate\Http\Request $request
     *
     * @return array
     */
    public function suggest(Request $request)
    {
        $therapistId = $request->get('therapist_id');
        if (!Auth::user() && !$therapistId) {
            return ['success' => false, 'message' => 'error_message.questionnaire_suggest'];
        }

        $data = json_decode($request->get('data'));

        $foundQuestionnaire = Questionnaire::find($data->id);

        if (!$foundQuestionnaire || !$foundQuestionnaire->auto_translated || (int) $foundQuestionnaire->therapist_id === (int) $therapistId) {
            return ['success' => false, 'message' => 'error_message.questionnaire_suggest'];
        }


        DB::beginTransaction();
        try {
            $data = json_decode($request->get('data'));
            $questionnaire = Questionnaire::create([
                'title' => $data->title,
                'description' => $data->description,
                'therapist_id' => $therapistId,
                'parent_id' => $foundQuestionnaire->id,
                'global' => env('APP_NAME') == 'hi',
                'suggested_lang' => App::getLocale(),
            ]);

            $questions = $data->questions;
            foreach ($questions as $index => $question) {
                $newQuestion = Question::create([
                    'title' => $question->title,
                    'type' => $question->type,
                    'questionnaire_id' => $questionnaire->id,
                    'file_id' => $question->file ? $question->file->id : null,
                    'order' => $index,
                    'parent_id' => $question->id,
                    'suggested_lang' => App::getLocale(),
                ]);

                if (isset($question->answers)) {
                    foreach ($question->answers as $answer) {
                        Answer::create([
                            'description' => $answer->description,
                            'question_id' => $newQuestion->id,
                            'parent_id' => $answer->id,
                            'suggested_lang' => App::getLocale(),
                        ]);
                    }
                }
            }

            DB::commit();
            return ['success' => true, 'message' => 'success_message.questionnaire_suggest'];
        } catch (\Exception $e) {
            DB::rollBack();
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }

    /**
     * @param \Illuminate\Http\Request $request
     * @param \App\Models\Questionnaire $questionnaire
     *
     * @return array
     */
    public function approveTranslation(Request $request, Questionnaire $questionnaire)
    {
        $parentQuestionnaire = Questionnaire::find($questionnaire->parent_id);

        if (!$parentQuestionnaire) {
            return ['success' => false, 'message' => 'error_message.questionnaire_update'];
        }

        DB::beginTransaction();
        try {
            $data = json_decode($request->get('data'));

            $parentQuestionnaire->update([
                'title' => $data->title,
                'description' => $data->description,
                'auto_translated' => false,
            ]);

            $questions = $data->questions;
            foreach ($questions as $index => $question) {
                $foundQuestion = Question::find($question->parent_id);
                if ($foundQuestion) {
                    $foundQuestion->update([
                        'title' => $question->title,
                    ]);

                    if ($question->answers) {
                        foreach ($question->answers as $answer) {
                            $foundAnswer = Answer::find($answer->parent_id);
                            if ($foundAnswer) {
                                $foundAnswer->update([
                                    'description' => $answer->description,
                                ]);
                            }
                        }

                        // Remove submitted translation remaining.
                        Answer::where('suggested_lang', App::getLocale())
                            ->where('parent_id', $answer->parent_id)
                            ->delete();
                    }
                }

                // Remove submitted translation remaining.
                Question::where('suggested_lang', App::getLocale())
                    ->where('parent_id', $question->parent_id)
                    ->delete();
            }

            // Remove submitted translation remaining.
            Questionnaire::where('suggested_lang', App::getLocale())
                ->where('parent_id', $questionnaire->parent_id)
                ->delete();

            DB::commit();
            return ['success' => true, 'message' => 'success_message.questionnaire_update'];
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
            $languages = Language::where('code', '<>', config('app.fallback_locale'))->get();
            $translate = new GoogleTranslateHelper();

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

                if (!in_array($questionObj->id, (array) $noChangedFiles)) {
                    $oldFile = File::find($questionObj->file_id);

                    if ($oldFile) {
                        $oldFile->delete();
                    }

                    $file_index_key = array_keys($files);

                    if ($files && array_key_exists($file_index_key[$index], $files)) {
                        $file = FileHelper::createFile($files[$file_index_key[$index]], File::QUESTIONNAIRE_PATH);
                        $questionObj->update(['file_id' => $file ? $file->id : null]);
                    }
                }

                if ($questionObj->wasRecentlyCreated) {
                    foreach ($languages as $language) {
                        $languageCode = $language->code;

                        // Auto translate question.
                        $translatedTitle = $translate->translate($questionObj->title, $languageCode);
                        $questionObj->setTranslation('title', $languageCode, $translatedTitle);
                        $questionObj->setTranslation('auto_translated', $languageCode, true);
                        $questionObj->save();
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

                        if ($answerObj->wasRecentlyCreated) {
                            foreach ($languages as $language) {
                                $languageCode = $language->code;

                                // Auto translate answer.
                                $translatedAnswerDescription = $translate->translate($answerObj->description, $languageCode);
                                $answerObj->setTranslation('description', $languageCode, $translatedAnswerDescription);
                                $answerObj->setTranslation('auto_translated', $languageCode, true);
                                $answerObj->save();
                            }
                        }
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
     * @return \App\Models\Questionnaire[]|\Illuminate\Database\Eloquent\Collection
     */
    public function getQuestionnairesForOpenLibrary()
    {
        $categories = Category::where('hi_only', true)->get();
        $query = Questionnaire::withTrashed()->where('global', true);
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
     * @return mixed
     */
    public function getQuestionnaireCategoriesForOpenLibrary(Request $request)
    {
        return QuestionnaireCategory::where('questionnaire_id', $request->get('id'))->get();
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
