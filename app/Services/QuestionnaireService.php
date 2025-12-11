<?php

namespace App\Services;

use App\Events\ApplyQuestionnaireAutoTranslationEvent;
use App\Helpers\FileHelper;
use App\Helpers\GoogleTranslateHelper;
use App\Models\Answer;
use App\Models\File;
use App\Models\Language;
use App\Models\Question;
use App\Models\Questionnaire;
use App\Models\QuestionnaireCategory;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class QuestionnaireService
{
    /**
     * @param $data
     * @param $files
     * @param $therapistId
     * @return array
     */
    public function create($data, $files, $therapistId = null)
    {
        DB::beginTransaction();

        try {
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
                    'share_to_hi_library' => false,
                    'therapist_id' => $therapistId,
                    'share_with_phc_worker' => $data->share_with_phc_worker ?? false,
                    'global' => env('APP_NAME') == 'hi',
                ]);
            } else {
                $questionnaire = Questionnaire::create([
                    'title' => $data->title,
                    'description' => $data->description ?? [],
                    'share_to_hi_library' => $data->share_to_hi_library ?? false,
                    'therapist_id' => $therapistId,
                    'global' => env('APP_NAME') == 'hi',
                    'include_at_the_start' => $data->include_at_the_start ?? false,
                    'include_at_the_end' => $data->include_at_the_end ?? false,
                    'share_with_phc_worker' => $data->share_with_phc_worker ?? false,
                    'is_survey' => $data->is_survey ?? false,
                ]);
            }

            // Attach category to questionnaire.
            $categories = $data->categories ?? [];
            foreach ($categories as $category) {
                $questionnaire->categories()->attach($category);
            }

            $questions = $data->questions;

            foreach ($questions as $index => $question) {
                $file = null;
                if (array_key_exists($index, $files)) {
                    $file = FileHelper::createFile($files[$index], File::QUESTIONNAIRE_PATH);
                } else if (!empty($question->file?->id)) {
                    // CLone file
                    $originalFile = File::findOrFail($question->file->id);
                    $file = FileHelper::replicateFile($originalFile);
                }
                $newQuestion = Question::create([
                    'title' => $question->title,
                    'type' => $question->type,
                    'questionnaire_id' => $questionnaire->id,
                    'file_id' => $file?->id,
                    'order' => $index,
                    'mark_as_countable' => $question->mark_as_countable ?? false,
                    'mandatory' => isset($question->mandatory) && is_bool($question->mandatory) ? $question->mandatory : false,
                ]);

                if (isset($question->answers)) {
                    foreach ($question->answers as $answer) {
                        Answer::create([
                            'description' => $answer->description ?? [],
                            'question_id' => $newQuestion->id,
                            'value' => isset($answer->value) && is_numeric($answer->value) ? $answer->value : null,
                            'threshold' => isset($answer->threshold) && is_numeric($answer->threshold) ? $answer->threshold : null,
                        ]);
                    }
                }
            }

            DB::commit();

            // Add automatic translation for Exercise.
            try {
                event(new ApplyQuestionnaireAutoTranslationEvent($questionnaire));
            } catch (\Exception $e) {
                Log::warning("Translation failed: " . $e->getMessage());
            }

            return ['success' => true, 'message' => 'success_message.questionnaire_create', 'id' => $questionnaire->id];
        } catch (\Exception $e) {
            DB::rollBack();
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }

    /**
     * @param Questionnaire $questionnaire
     * @param $data
     * @param $files
     * @param $noFileQuestions
     * @return array
     */
    public function update(Questionnaire $questionnaire, $data, $files, $noFileQuestions)
    {
        DB::beginTransaction();

        try {
            $questionnaire->update([
                'title' => $data->title,
                'description' => $data->description,
                'share_to_hi_library' => $data->share_to_hi_library ?? false,
                'share_with_phc_worker' => $data->share_with_phc_worker ?? false,
                'include_at_the_start' => $data->include_at_the_start ?? false,
                'include_at_the_end' => $data->include_at_the_end ?? false,
            ]);

            // Attach category to exercise.
            $categories = $data->categories ?? [];
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
                        'mark_as_countable' => $question->mark_as_countable ?? false,
                        'mandatory' => $question->mandatory ?? false,
                    ]
                );

                if (in_array($questionObj->id, (array) $noFileQuestions)) {
                    if ($questionObj->file_id) {
                        File::find($questionObj->file_id)?->delete();
                    }
                }

                if (array_key_exists($index, $files)) {
                    if ($questionObj->file_id) {
                        File::find($questionObj->file_id)?->delete();
                    }

                    $file = FileHelper::createFile($files[$index], File::QUESTIONNAIRE_PATH);
                    $questionObj->update(['file_id' => $file?->id]);
                }

                if ($questionObj->wasRecentlyCreated) {
                    foreach ($languages as $language) {
                        $languageCode = $language->code;

                        // Auto translate question.
                        try {
                            $translatedTitle = $translate->translate($questionObj->title, $languageCode);
                            $questionObj->setTranslation('title', $languageCode, $translatedTitle);
                            $questionObj->setTranslation('auto_translated', $languageCode, true);
                            $questionObj->save();
                        } catch (\Exception $e) {
                            Log::warning("Translation failed: " . $e->getMessage());
                        }
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
                                'description' => $answer->description ?? [],
                                'question_id' => $questionObj->id,
                                'value' => isset($answer->value) && is_numeric($answer->value) ? $answer->value : null,
                                'threshold' => isset($answer->threshold) && is_numeric($answer->threshold) ? $answer->threshold : null,
                            ]
                        );

                        $answerIds[] = $answerObj->id;

                        if ($answerObj->wasRecentlyCreated) {
                            foreach ($languages as $language) {
                                $languageCode = $language->code;

                                // Auto translate answer.
                                try {
                                    $translatedAnswerDescription = $translate->translate($answerObj->description, $languageCode);
                                    $answerObj->setTranslation('description', $languageCode, $translatedAnswerDescription);
                                    $answerObj->setTranslation('auto_translated', $languageCode, true);
                                    $answerObj->save();
                                } catch (\Exception $e) {
                                    Log::warning("Translation failed: " . $e->getMessage());
                                }
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
}
