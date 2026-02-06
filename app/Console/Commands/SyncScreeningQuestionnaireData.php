<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Helpers\GlobalDataSyncHelper;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use App\Models\File;
use App\Models\ScreeningQuestionnaireQuestion;
use App\Models\ScreeningQuestionnaireQuestionOption;
use Illuminate\Support\Facades\Storage;
use Spatie\Activitylog\Facades\Activity;

class SyncScreeningQuestionnaireData extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'hi:sync-screening-questionnaire-data';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Sync screening questionnaire data from global to other organization';

    /**
     * @return void
     * @throws \Illuminate\Http\Client\RequestException
     */
    public function handle()
    {
        if (env('APP_NAME') === 'hi') {
            $this->info('Skipping sync on global instance.');
            return;
        }

        // Disable activity logging for data sync
        Activity::disableLogging();

        $this->alert('Starting screening questionnaire sync...');

        // Fetch screening questionnaires from global
        $globalScreeningQuestionnaires = GlobalDataSyncHelper::fetchData('get-screening-questionnaires');
        if (is_null($globalScreeningQuestionnaires)) {
            $this->error('Failed to fetch screening questionnaires from global.');
            return;
        }

        $this->output->progressStart(count($globalScreeningQuestionnaires));
        foreach ($globalScreeningQuestionnaires as $globalScreeningQuestionnaire) {
            // Upsert screening questionnaire
            DB::table('screening_questionnaires')->updateOrInsert(
                ['id' => $globalScreeningQuestionnaire->id],
                [
                    'title' => json_encode($globalScreeningQuestionnaire->title),
                    'description' => json_encode($globalScreeningQuestionnaire->description),
                    'published_date' => $globalScreeningQuestionnaire->published_date,
                    'status' => $globalScreeningQuestionnaire->status,
                    'auto_translated' => json_encode($globalScreeningQuestionnaire->auto_translated),
                    'created_at' => Carbon::parse($globalScreeningQuestionnaire->created_at ?? now()),
                    'updated_at' => Carbon::now(),
                    'deleted_at' => $globalScreeningQuestionnaire->deleted_at ? Carbon::parse($globalScreeningQuestionnaire->deleted_at) : null,
                ]
            );

            foreach ($globalScreeningQuestionnaire->sections as $section) {
                // Upsert screening questionnaire section
                DB::table('screening_questionnaire_sections')->updateOrInsert(
                    ['id' => $section->id],
                    [
                        'questionnaire_id' => $globalScreeningQuestionnaire->id,
                        'title' => json_encode($section->title),
                        'description' => json_encode($section->description),
                        'order' => $section->order,
                        'auto_translated' => json_encode($section->auto_translated),
                        'created_at' => Carbon::parse($section->created_at ?? now()),
                        'updated_at' => Carbon::now(),
                    ]
                );

                // Upsert section action
                foreach ($section->actions as $action) {
                    DB::table('screening_questionnaire_actions')->updateOrInsert(
                        ['id' => $action->id],
                        [
                            'section_id' => $section->id,
                            'from' => $action->from,
                            'to' => $action->to,
                            'action_text' => json_encode($action->action_text),
                            'created_at' => Carbon::parse($action->created_at ?? now()),
                            'updated_at' => Carbon::now(),
                        ]
                    );
                }

                // Upsert questions
                foreach ($section->questions as $question) {
                    $fileId = null;
                    $oldQuestionFileId = null;
                    // Get existing local question file 
                    $existingQuestion = ScreeningQuestionnaireQuestion::find($question->id);
                    if ($existingQuestion && $existingQuestion->file_id) {
                        $oldQuestionFileId = $existingQuestion->file_id;
                    }
                    // Fetch and store the associated file from global
                    if ($question->file) {
                        $file = $question->file;
                        $fileId = self::storeFile($file, File::SCREENING_QUESTIONNAIRE_PATH);
                    }

                    DB::table('screening_questionnaire_questions')->updateOrInsert(
                        ['id' => $question->id],
                        [
                            'questionnaire_id' => $globalScreeningQuestionnaire->id,
                            'section_id' => $section->id,
                            'question_text' => json_encode($question->question_text),
                            'question_type' => $question->question_type,
                            'mandatory' => $question->mandatory,
                            'order' => $question->order,
                            'file_id' => $fileId,
                            'auto_translated' => json_encode($question->auto_translated),
                            'created_at' => Carbon::parse($question->created_at ?? now()),
                            'updated_at' => Carbon::now(),
                        ]
                    );

                    // Delete old file
                    self::deleteFile($oldQuestionFileId);

                    // Upsert question options and logics
                    foreach ($question->options as $option) {
                        $fileId = null;
                        $oldFileId = null;
                        // Get existing local option file 
                        $existingOption = ScreeningQuestionnaireQuestionOption::find($option->id);
                        if ($existingOption && $existingOption->file_id) {
                            $oldFileId = $existingOption->file_id;
                        }
                        // Fetch and store the associated file from global
                        if ($option->file) {
                            $file = $option->file;
                            $fileId = self::storeFile($file, File::SCREENING_QUESTIONNAIRE_PATH);
                        }

                        DB::table('screening_questionnaire_question_options')->updateOrInsert(
                            ['id' => $option->id],
                            [
                                'question_id' => $question->id,
                                'option_text' => json_encode($option->option_text),
                                'option_point' => $option->option_point,
                                'threshold' => $option->threshold,
                                'min' => $option->min,
                                'max' => $option->max,
                                'min_note' => json_encode($option->min_note),
                                'max_note' => json_encode($option->max_note),
                                'file_id' => $fileId,
                                'ref' => $option->ref,
                                'auto_translated' => json_encode($option->auto_translated),
                                'created_at' => Carbon::parse($option->created_at ?? now()),
                                'updated_at' => Carbon::now(),
                            ]
                        );

                        // Delete old file
                        self::deleteFile($oldFileId);
                    }

                    foreach ($question->logics as $logic) {
                        DB::table('screening_questionnaire_question_logics')->updateOrInsert(
                            ['id' => $logic->id],
                            [
                                'question_id' => $question->id,
                                'target_question_id' => $logic->target_question_id,
                                'target_option_id' => $logic->target_option_id,
                                'target_option_value' => $logic->target_option_value,
                                'condition_type' => $logic->condition_type,
                                'condition_rule' => $logic->condition_rule,
                                'created_at' => Carbon::parse($logic->created_at ?? now()),
                                'updated_at' => Carbon::now(),
                            ]
                        );
                    }
                }
            }
        }

        $this->output->progressFinish();
        // Re-enable activity logging after data sync
        Activity::enableLogging();

        $this->info('Screening questionnaire sync completed successfully!');
    }

    /**
     * Store a file from global and return the local file ID.
     *
     * @param object $file
     * @param string $fileDir
     * @return int|null
     */
    private static function storeFile($file, $fileDir)
    {
        try {
            $file_url = env('GLOBAL_ADMIN_SERVICE_URL') . '/file/' . $file->id;
            $filePath = $fileDir . '/' . $file->filename;
            $fileContent = file_get_contents($file_url);
            $localFile = File::create([
                'filename' => $file->filename,
                'path' => $filePath,
                'content_type' => $file->content_type,
            ]);

            Storage::put($filePath, $fileContent);
            $fileId = $localFile->id;
        } catch (\Exception $e) {
            Log::debug("Failed to store associated file from global {$file->id}: " . $e->getMessage());
        }
        return $fileId ?? null;
    }

    /**
     * Delete a file by its ID.
     *
     * @param int $fileId
     * @return void
     */
    private static function deleteFile($fileId)
    {
        try {
            $file = File::find($fileId);
            if ($file) {
                Storage::delete($file->path);
                $file->delete();
            }
        } catch (\Exception $e) {
            Log::debug("Failed to delete file {$fileId}: " . $e->getMessage());
        }
    }   
}
