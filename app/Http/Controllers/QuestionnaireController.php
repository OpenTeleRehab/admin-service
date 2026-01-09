<?php

namespace App\Http\Controllers;

use App\Helpers\ContentHelper;
use App\Helpers\LanguageHelper;
use App\Http\Resources\QuestionnaireListResource;
use App\Http\Resources\QuestionnaireResource;
use App\Models\Answer;
use App\Models\Forwarder;
use App\Models\Question;
use App\Models\Questionnaire;
use App\Models\QuestionnaireCategory;
use App\Models\SystemLimit;
use App\Models\User;
use App\Services\QuestionnaireService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;

class QuestionnaireController extends Controller
{
    protected $questionnaireService;

    public function __construct(QuestionnaireService $questionnaireService)
    {
        $this->questionnaireService = $questionnaireService;
    }

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
        $authUser = Auth::user();
        $therapistId = $request->get('therapist_id');
        $filter = json_decode($request->get('filter'), true);

        $query = Questionnaire::select('questionnaires.*')->where('questionnaires.parent_id', null)->where('is_survey', false);

        if ($authUser->type === User::GROUP_PHC_WORKER) {
            $query->where('share_with_phc_worker', true);
        }

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
            $authUser = Auth::user();
            $translatorLanguages = $authUser->translatorLanguages->pluck('code');

            $query->whereHas('children', function ($q) use ($translatorLanguages) {
                $q->whereIn('suggested_lang', $translatorLanguages);
            });
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
            'data' => QuestionnaireListResource::collection($questionnaires),
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

        $files = $request->allFiles();
        $data = json_decode($request->get('data'));

        return $this->questionnaireService->create($data, $files, $therapistId);
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
                    'mandatory' => $question->mandatory,
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
        LanguageHelper::validateAssignedLanguageCode($questionnaire->suggested_lang);

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
        LanguageHelper::validateAssignedLanguageCode($questionnaire->suggested_lang);

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
        LanguageHelper::validateAssignedLanguage($request->get('lang'));

        $therapistId = $request->get('therapist_id');
        if (!Auth::user() && !$therapistId) {
            return ['success' => false, 'message' => 'error_message.questionnaire_update'];
        }

        if ((int) $questionnaire->therapist_id !== (int) $therapistId) {
            return ['success' => false, 'message' => 'error_message.questionnaire_update'];
        }

        $files = $request->allFiles();
        $data = json_decode($request->get('data'));
        $noFileQuestions = $request->get('no_file_questions', []);

        return $this->questionnaireService->update($questionnaire, $data, $files, $noFileQuestions);
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
        return Questionnaire::withTrashed()->whereNull('therapist_id')->where('is_survey', false)->get();
    }

    /**
     * @return \App\Models\Questionnaire[]|\Illuminate\Database\Eloquent\Collection
     */
    public function getQuestionnairesForOpenLibrary()
    {
        $query = Questionnaire::withTrashed()
            ->where('global', true)
            ->where('share_to_hi_library', true);

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

    /**
     * @param \Illuminate\Http\Request $request
     *
     * @return \App\Http\Resources\QuestionnaireResource
     */
    public function getById(Request $request)
    {
        $questionnaireId = $request->get('id');
        $questionnaire = Questionnaire::find($questionnaireId);
        return new QuestionnaireResource($questionnaire);
    }

    /**
     * @param \Illuminate\Http\Request $request
     *
     * @return \Illuminate\Http\Resources\Json\AnonymousResourceCollection
     */
    public function getByTherapist(Request $request)
    {
        $questionnaires = Questionnaire::where('is_survey', false)
            ->where(function ($query) use ($request) {
                $query->where('therapist_id', $request->get('therapist_id'))
                    ->orWhere('therapist_id', null);
            })->get();
        return QuestionnaireResource::collection($questionnaires);
    }

    /**
     * @param \Illuminate\Http\Request $request
     *
     * @return \Illuminate\Http\Resources\Json\AnonymousResourceCollection
     */
    public function getByClinicAdmin(Request $request)
    {
        $clinicAdmin = User::findOrFail($request->get('clinic_admin_id'));
        $access_token = Forwarder::getAccessToken(Forwarder::THERAPIST_SERVICE);
        $response = Http::withToken($access_token)->get(env('THERAPIST_SERVICE_URL') . '/therapist/list/by-clinic-id', [
            'clinic_id' => $clinicAdmin->clinic_id,
        ]);
        $therapists = $response->json('data');
        $ids = collect($therapists)->pluck('id')->all();
        $questionnaires = Questionnaire::where('is_survey', false)
                ->where(function ($query) use ($ids) {
                    $query->whereIn('therapist_id', $ids)
                    ->orWhere('therapist_id', null);
                })->get();
        return QuestionnaireResource::collection($questionnaires);
    }

    /**
     * @param \Illuminate\Http\Request $request
     *
     * @return \Illuminate\Http\Resources\Json\AnonymousResourceCollection
     */
    public function getByCountryAdmin(Request $request)
    {
        $countryAdmin = User::findOrFail($request->get('country_admin_id'));
        $access_token = Forwarder::getAccessToken(Forwarder::THERAPIST_SERVICE);
        $response = Http::withToken($access_token)->get(env('THERAPIST_SERVICE_URL') . '/therapist/list/by-country-id', [
            'country_id' => $countryAdmin->country_id,
        ]);
        $therapists = $response->json('data');
        $ids = collect($therapists)->pluck('id')->all();
        $questionnaires = Questionnaire::where('is_survey', false)
            ->where(function ($query) use ($ids) {
                $query->whereIn('therapist_id', $ids)
                    ->orWhere('therapist_id', null);
            })->get();
        return QuestionnaireResource::collection($questionnaires);
    }
}
