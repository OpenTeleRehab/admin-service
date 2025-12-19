<?php

namespace App\Http\Controllers;

use App\Enums\ScreeningQuestionnaireStatus;
use App\Helpers\FileHelper;
use App\Http\Requests\StoreScreeningQuestionnaireRequest;
use App\Http\Requests\SubmitScreeningQuestionnaireRequest;
use App\Http\Requests\UpdateScreeningQuestionnaireRequest;
use App\Http\Resources\ScreeningQuestionnaireResource;
use App\Models\File;
use App\Models\ScreeningQuestionnaire;
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
                            'question_id' => $question->id,
                            'file_id' => $fileId,
                        ]);
                    }

                    if ($questionIndex >= 1) {
                        foreach ($questionItem['logics'] ?? [] as $logicItem) {
                            if ($logicItem['target_question_id']) {
                                $questionIds = array_column($sectionItem['questions'], 'id');
                                $targetQuestionIndex = array_search($logicItem['target_question_id'], $questionIds);

                                if ($section->questions[$targetQuestionIndex]?->id) {
                                    $targetQuestions = $section->questions[$targetQuestionIndex];
                                    $targetOptionIndex = $logicItem['target_option_id'];

                                    ScreeningQuestionnaireQuestionLogic::create([
                                        'question_id' => $question->id,
                                        'target_question_id' => $targetQuestions->id,
                                        'target_option_id' => $targetQuestions->options[$targetOptionIndex]?->id ?? null,
                                        'condition_type' => $logicItem['condition_type'],
                                        'condition_rule' => $logicItem['condition_rule'],
                                    ]);
                                }
                            }
                        }
                    }
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
                ScreeningQuestionnaireSection::updateOrCreate(
                    [
                        'id' => $sectionItem['id'],
                    ],
                    [
                        'title' => $sectionItem['title'],
                        'description' => $sectionItem['description'],
                        'order' => $sectionIndex + 1,
                    ],
                );

                foreach ($sectionItem['questions'] ?? [] as $questionIndex => $questionItem) {
                    $file = $questionItem['file'] ?? null;

                    if (isset($allFiles['sections'][$sectionIndex]['questions'][$questionIndex]['file'])) {
                        $file = FileHelper::createFile(
                            $allFiles['sections'][$sectionIndex]['questions'][$questionIndex]['file'],
                            File::SCREENING_QUESTIONNAIRE_PATH,
                        );
                    }

                    ScreeningQuestionnaireQuestion::updateOrCreate(
                        [
                            'id' => $questionItem['id'],
                        ],
                        [
                            'question_text' => $questionItem['question_text'],
                            'question_type' => $questionItem['question_type'],
                            'mandatory' => (bool) $questionItem['mandatory'],
                            'order' => $questionIndex + 1,
                            'file_id' => $file['id'] ?? null,
                        ],
                    );

                    foreach ($questionItem['options'] ?? [] as $optionIndex => $optionItem) {
                        $file = $optionItem['file'] ?? null;

                        if (isset($allFiles['sections'][$sectionIndex]['questions'][$questionIndex]['options'][$optionIndex]['file'])) {
                            $file = FileHelper::createFile(
                                $allFiles['sections'][$sectionIndex]['questions'][$questionIndex]['options'][$optionIndex]['file'],
                                File::SCREENING_QUESTIONNAIRE_PATH,
                            );
                        }

                        ScreeningQuestionnaireQuestionOption::updateOrCreate(
                            [
                                'id' => $optionItem['id'],
                            ],
                            [
                                'option_text' => $optionItem['option_text'] ?? '',
                                'option_point' => $optionItem['option_point'] ?? null,
                                'threshold' => $optionItem['threshold'] ?? null,
                                'min' => $optionItem['min'] ?? null,
                                'max' => $optionItem['max'] ?? null,
                                'file_id' => $file['id'] ?? null,
                            ],
                        );
                    }

                    if ($questionIndex >= 1) {
                        foreach ($questionItem['logics'] ?? [] as $logicItem) {
                            if ($logicItem['target_question_id']) {
                                // TODO: Set new target_option_id for new question option.
                                ScreeningQuestionnaireQuestionLogic::updateOrCreate(
                                    [
                                        'id' => $logicItem['id'],
                                    ],
                                    [
                                        'question_id' => $questionItem['id'],
                                        'target_question_id' => $logicItem['target_question_id'],
                                        'target_option_id' => $logicItem['target_option_id'],
                                        'condition_type' => $logicItem['condition_type'],
                                        'condition_rule' => $logicItem['condition_rule'],
                                    ]
                                );
                            }
                        }
                    }
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
        $screeningQuestionnaire->answer()->create([
            'questionnaire_id' => $screeningQuestionnaire->id,
            'user_id' => $request->integer('user_id'),
            'answers' => $request->get('answers'),
        ]);

        return response()->json([
            'success' => true,
            'message' => 'success_message.questionnaire_submit',
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
     * @return void
     */
    public function destroy($id)
    {
        //
    }
}
