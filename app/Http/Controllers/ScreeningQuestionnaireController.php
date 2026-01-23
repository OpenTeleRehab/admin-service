<?php

namespace App\Http\Controllers;

use App\Enums\ScreeningQuestionnaireStatus;
use App\Helpers\FileHelper;
use App\Http\Requests\ListHistoryScreeningQuestionnaireRequest;
use App\Http\Requests\StoreScreeningQuestionnaireRequest;
use App\Http\Requests\SubmitScreeningQuestionnaireRequest;
use App\Http\Requests\UpdateScreeningQuestionnaireRequest;
use App\Http\Resources\ScreeningQuestionnaireResource;
use App\Models\File;
use App\Models\ScreeningQuestionnaire;
use App\Models\ScreeningQuestionnaireAction;
use App\Models\ScreeningQuestionnaireAnswer;
use App\Models\ScreeningQuestionnaireQuestion;
use App\Models\ScreeningQuestionnaireQuestionLogic;
use App\Models\ScreeningQuestionnaireQuestionOption;
use App\Models\ScreeningQuestionnaireSection;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ScreeningQuestionnaireController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function index(Request $request)
    {
        return response()->json([
            'success' => true,
            'data' => ScreeningQuestionnaireResource::collection(ScreeningQuestionnaire::all()),
        ]);
    }


    /**
     * Display a published listing of the resource.
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function getScreeningQuestionnarieList(Request $request)
    {
        $questionnaires = ScreeningQuestionnaire::where('status', 'published')->get();

        return response()->json([
            'success' => true,
            'data' => ScreeningQuestionnaireResource::collection($questionnaires),
        ]);
    }


    /**
     * Store a newly created resource in storage.
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function store(StoreScreeningQuestionnaireRequest $request)
    {
        DB::beginTransaction();

        try {
            $allFiles = $request->allFiles();

            $screeningQuestionnaire = ScreeningQuestionnaire::create($request->validated());

            $sections = json_decode($request->get('sections'), true);

            foreach ($sections ?? [] as $sectionIndex => $sectionItem) {
                $section = ScreeningQuestionnaireSection::create([
                    'title' => $sectionItem['title'],
                    'description' => $sectionItem['description'],
                    'order' => $sectionIndex + 1,
                    'questionnaire_id' => $screeningQuestionnaire->id,
                ]);

                foreach ($sectionItem['questions'] ?? [] as $questionIndex => $questionItem) {
                    $fileId = null;

                    if (isset($allFiles['sections'][$sectionIndex]['questions'][$questionIndex]['file'])) {
                        $file = FileHelper::createFile(
                            $allFiles['sections'][$sectionIndex]['questions'][$questionIndex]['file'],
                            File::SCREENING_QUESTIONNAIRE_PATH,
                        );

                        $fileId = $file?->id ?? null;
                    }

                    $question = ScreeningQuestionnaireQuestion::create([
                        'question_text' => $questionItem['question_text'],
                        'question_type' => $questionItem['question_type'],
                        'mandatory' => (bool) $questionItem['mandatory'],
                        'order' => $questionIndex + 1,
                        'section_id' => $section->id,
                        'questionnaire_id' => $screeningQuestionnaire->id,
                        'file_id' => $fileId,
                    ]);

                    foreach ($questionItem['options'] ?? [] as $optionIndex => $optionItem) {
                        $fileId = null;

                        if (isset($allFiles['sections'][$sectionIndex]['questions'][$questionIndex]['options'][$optionIndex]['file'])) {
                            $file = FileHelper::createFile(
                                $allFiles['sections'][$sectionIndex]['questions'][$questionIndex]['options'][$optionIndex]['file'],
                                File::SCREENING_QUESTIONNAIRE_PATH,
                            );

                            $fileId = $file?->id ?? null;
                        }

                        ScreeningQuestionnaireQuestionOption::create([
                            'option_text' => $optionItem['option_text'] ?? '',
                            'option_point' => $optionItem['option_point'] ?? null,
                            'threshold' => $optionItem['threshold'] ?? null,
                            'min' => $optionItem['min'] ?? null,
                            'max' => $optionItem['max'] ?? null,
                            'min_note' => $optionItem['min_note'] ?? '',
                            'max_note' => $optionItem['max_note'] ?? '',
                            'question_id' => $question->id,
                            'file_id' => $fileId,
                            'ref' => $optionItem['id'], // For logic target option finding.
                        ]);
                    }

                    if ($questionIndex >= 1) {
                        foreach ($questionItem['logics'] ?? [] as $logicItem) {
                            if ($logicItem['target_question_id'] && $logicItem['condition_rule']) {
                                $questionIds = array_column($sectionItem['questions'], 'id');
                                $targetQuestionIndex = array_search($logicItem['target_question_id'], $questionIds);

                                if ($section->questions[$targetQuestionIndex]) {
                                    $targetQuestion = $section->questions[$targetQuestionIndex];
                                    $targetOption = ScreeningQuestionnaireQuestionOption::firstWhere('ref', $logicItem['target_option_id']);

                                    ScreeningQuestionnaireQuestionLogic::create([
                                        'question_id' => $question->id,
                                        'target_question_id' => $targetQuestion->id,
                                        'target_option_id' => $targetOption?->id ?? null,
                                        'target_option_value' => $logicItem['target_option_value'] ?? null,
                                        'condition_type' => $logicItem['condition_type'],
                                        'condition_rule' => $logicItem['condition_rule'],
                                    ]);
                                }
                            }
                        }
                    }
                }

                foreach ($sectionItem['actions'] ?? [] as $actionItem) {
                    ScreeningQuestionnaireAction::create([
                        'section_id' => $section->id,
                        'from' => $actionItem['from'],
                        'to' => $actionItem['to'],
                        'action_text' => $actionItem['action_text'],
                    ]);
                }
            }

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'success_message.questionnaire_create',
            ]);
        } catch (\Exception $e) {
            DB::rollBack();

            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Display the specified resource.
     *
     * @param ScreeningQuestionnaire $screeningQuestionnaire
     * @return ScreeningQuestionnaireResource
     */
    public function show(ScreeningQuestionnaire $screeningQuestionnaire)
    {
        return new ScreeningQuestionnaireResource($screeningQuestionnaire);
    }

    /**
     * Update the specified resource in storage.
     *
     * @param UpdateScreeningQuestionnaireRequest $request
     * @param ScreeningQuestionnaire $screeningQuestionnaire
     * @return \Illuminate\Http\JsonResponse
     */
    public function update(UpdateScreeningQuestionnaireRequest $request, ScreeningQuestionnaire $screeningQuestionnaire)
    {
        DB::beginTransaction();

        try {
            $allFiles = $request->allFiles();

            $screeningQuestionnaire->update([
                'title' => $request->get('title'),
                'description' => $request->get('description'),
            ]);

            $sections = json_decode($request->get('sections'), true);

            foreach ($sections ?? [] as $sectionIndex => $sectionItem) {
                $section = ScreeningQuestionnaireSection::updateOrCreate(
                    [
                        'id' => $sectionItem['id'],
                    ],
                    [
                        'title' => $sectionItem['title'],
                        'description' => $sectionItem['description'],
                        'order' => $sectionIndex + 1,
                        'questionnaire_id' => $screeningQuestionnaire->id,
                    ],
                );

                foreach ($sectionItem['questions'] ?? [] as $questionIndex => $questionItem) {
                    $fileId = $questionItem['file']['id'] ?? null;

                    if (isset($allFiles['sections'][$sectionIndex]['questions'][$questionIndex]['file'])) {
                        $file = FileHelper::createFile(
                            $allFiles['sections'][$sectionIndex]['questions'][$questionIndex]['file'],
                            File::SCREENING_QUESTIONNAIRE_PATH,
                        );

                        $fileId = $file?->id ?? null;
                    }

                    $question = ScreeningQuestionnaireQuestion::updateOrCreate(
                        [
                            'id' => $questionItem['id'],
                        ],
                        [
                            'question_text' => $questionItem['question_text'],
                            'question_type' => $questionItem['question_type'],
                            'mandatory' => (bool) $questionItem['mandatory'],
                            'order' => $questionIndex + 1,
                            'section_id' => $section->id,
                            'questionnaire_id' => $screeningQuestionnaire->id,
                            'file_id' => $fileId,
                        ],
                    );

                    foreach ($questionItem['options'] ?? [] as $optionIndex => $optionItem) {
                        $fileId = $optionItem['file']['id'] ?? null;

                        if (isset($allFiles['sections'][$sectionIndex]['questions'][$questionIndex]['options'][$optionIndex]['file'])) {
                            $file = FileHelper::createFile(
                                $allFiles['sections'][$sectionIndex]['questions'][$questionIndex]['options'][$optionIndex]['file'],
                                File::SCREENING_QUESTIONNAIRE_PATH,
                            );

                            $fileId = $file?->id ?? null;
                        }

                        $existingQuestionOption = ScreeningQuestionnaireQuestionOption::where('id', $optionItem['id'])
                            ->where('question_id', $question->id)
                            ->first();

                        $questionOptionData = [
                            'option_text' => $optionItem['option_text'] ?? '',
                            'option_point' => $optionItem['option_point'] ?? null,
                            'threshold' => $optionItem['threshold'] ?? null,
                            'min' => $optionItem['min'] ?? null,
                            'max' => $optionItem['max'] ?? null,
                            'min_note' => $optionItem['min_note'] ?? '',
                            'max_note' => $optionItem['max_note'] ?? '',
                            'file_id' => $fileId,
                        ];

                        if ($existingQuestionOption) {
                            $questionOptionData = [
                                ...$questionOptionData,
                                'ref' => $existingQuestionOption->ref,
                            ];
                        } else {
                            $questionOptionData = [
                                ...$questionOptionData,
                                'ref' => $optionItem['id'],
                            ];
                        }

                        ScreeningQuestionnaireQuestionOption::updateOrCreate(
                            [
                                'id' => $optionItem['id'],
                                'question_id' => $question->id,
                            ],
                            $questionOptionData,
                        );
                    }

                    // Get existing logic ids.
                    $logicIds = $question->logics->pluck('id')->toArray();

                    $targetLogicIds = collect($questionItem['logics'])->pluck('id')->toArray();

                    // Find logic ids that exist in the database but not in the submitted data.
                    $diffLogicIds = array_diff($logicIds, $targetLogicIds);

                    // Delete orphaned logics that are no longer in the submitted data.
                    if (!empty($diffLogicIds)) {
                        $question->logics()->whereIn('id', $diffLogicIds)->delete();
                    }

                    foreach ($questionItem['logics'] ?? [] as $logicItem) {
                        if ($logicItem['target_question_id'] && $logicItem['condition_rule']) {
                            $questions = ScreeningQuestionnaireQuestion::where('section_id', $section->id)->get();
                            $questionIds = array_column($sectionItem['questions'], 'id');
                            $targetQuestionIndex = array_search($logicItem['target_question_id'], $questionIds);

                            if ($questions[$targetQuestionIndex]) {
                                $targetQuestion = $questions[$targetQuestionIndex];
                                $targetOption = ScreeningQuestionnaireQuestionOption::where('id', $logicItem['target_option_id'])
                                    ->orWhere('ref', $logicItem['target_option_id'])
                                    ->first();

                                ScreeningQuestionnaireQuestionLogic::updateOrCreate(
                                    [
                                        'id' => $logicItem['id'],
                                    ],
                                    [
                                        'question_id' => $question->id,
                                        'target_question_id' => $targetQuestion->id,
                                        'target_option_id' => $targetOption?->id ?? null,
                                        'target_option_value' => $logicItem['target_option_value'],
                                        'condition_type' => $logicItem['condition_type'],
                                        'condition_rule' => $logicItem['condition_rule'],
                                    ]
                                );
                            }
                        }
                    }
                }

                foreach ($sectionItem['actions'] ?? [] as $actionItem) {
                    ScreeningQuestionnaireAction::updateOrCreate(
                        [
                            'id' => $actionItem['id'],
                        ],
                        [
                            'section_id' => $section->id,
                            'from' => $actionItem['from'],
                            'to' => $actionItem['to'],
                            'action_text' => $actionItem['action_text'],
                        ]
                    );
                }
            }

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'success_message.questionnaire_update',
            ]);
        } catch (\Exception $e) {
            DB::rollBack();

            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Submit the specified resource in storage.
     *
     * @param SubmitScreeningQuestionnaireRequest $request
     * @param ScreeningQuestionnaire $screeningQuestionnaire
     * @return \Illuminate\Http\JsonResponse
     */
    public function submit(SubmitScreeningQuestionnaireRequest $request, ScreeningQuestionnaire $screeningQuestionnaire)
    {
        $result = $screeningQuestionnaire->answers()->create([
            'questionnaire_id' => $screeningQuestionnaire->id,
            'user_id' => $request->integer('user_id'),
            'answers' => $request->get('answers'),
        ]);

        return response()->json([
            'success' => true,
            'message' => 'success_message.questionnaire_submit',
            'data'=> $result
        ]);
    }

    /**
     * Publish the specified resource in storage.
     *
     * @param ScreeningQuestionnaire $screeningQuestionnaire
     * @return \Illuminate\Http\JsonResponse
     */
    public function publish(ScreeningQuestionnaire $screeningQuestionnaire)
    {
        $screeningQuestionnaire->update([
            'status' => ScreeningQuestionnaireStatus::PUBLISHED,
            'published_date' => Carbon::now(),
        ]);

        return response()->json([
            'success' => true,
            'message' => 'success_message.questionnaire_published',
        ]);
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param $id
     * @return \Illuminate\Http\JsonResponse
     */
    public function destroy(ScreeningQuestionnaire $screeningQuestionnaire)
    {
        $screeningQuestionnaire->delete();

        return response()->json([
            'success' => true,
            'message' => 'success_message.questionnaire_delete',
        ]);
    }

        /**
     * Get Interview History List the specified resource in storage.
     *
     * @param ListHistoryScreeningQuestionnaireRequest $request
     * @param ScreeningQuestionnaire $screeningQuestionnaire
     * @return \Illuminate\Http\JsonResponse
     */

    public function listHistoryScreeningQuestionnarie(ListHistoryScreeningQuestionnaireRequest $request)
    {
        $userId = $request->input('user_id');
        $questionnaireId = $request->input('questionnaire_id');

        $data = ScreeningQuestionnaireAnswer::
        where([
            'user_id' => $userId,
            'questionnaire_id' => $questionnaireId,
        ])->get();

        return response()->json([
            'success' => true,
            'message' => "success_get_interview_history_list",
            'data' => $data,
        ]);
    }

    /**
     * Get all published screening questionnaires.
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function getAllScreeningQuestionnaires()
    {
        $questionnaires = ScreeningQuestionnaire::with('answers')->where('status', 'published')->get();

        return response()->json([
            'success' => true,
            'data' => $questionnaires,
        ]);
    }

    /**
     * Get screening questionnaires for data sync.
     *
     * @return \Illuminate\Http\Resources\Json\AnonymousResourceCollection
     */
    public function getScreeningQuestionnaireForDataSync()
    {
        $questionnaires = ScreeningQuestionnaire::with([
            'sections.actions',
            'sections.questions.file',
            'sections.questions.options.file',
            'sections.questions.logics',
        ])
        ->withTrashed()
        ->where('status', 'published')
        ->get();

        return $questionnaires;
    }
}
