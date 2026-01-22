<?php

namespace App\Console\Commands;

use App\Helpers\GlobalDataSyncHelper;
use App\Models\File;
use App\Models\Question;
use App\Models\Questionnaire;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Spatie\Activitylog\Facades\Activity;

class SyncQuestionnaireData extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'hi:sync-questionnaire-data';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Sync questionnaires data from global to other organization';

    /**
     * @return void
     * @throws \Illuminate\Http\Client\RequestException
     */
    public function handle()
    {
        if (env('APP_NAME') != 'hi') {
            // Disable activity logging for data sync
            Activity::disableLogging();

            $this->alert('Starting questionnaire sync...');

            // Sync questionnaire data.
            $globalQuestionnaires = GlobalDataSyncHelper::fetchData('get-questionnaires');
            if (!$globalQuestionnaires) {
                $this->error('Failed to fetch questionnaires from global.');
                return;
            }
            $this->output->progressStart(count($globalQuestionnaires));
            $questionnaires = Questionnaire::withTrashed()->where('global', true)->get();
            // Remove data before import.
            if ($questionnaires) {
                foreach ($questionnaires as $questionnaire) {
                    $questions = Question::where('questionnaire_id', $questionnaire->id)->get();
                    // Remove files.
                    $removeFileIDs = $questions->pluck('file_id')->toArray();
                    foreach ($removeFileIDs as $removeFileID) {
                        $removeFile = File::find($removeFileID);
                        if ($removeFile) {
                            Storage::delete($removeFile->path);
                            $removeFile->delete();
                        }
                    }
                }
            }
            // Import global questionnaires to org.
            foreach ($globalQuestionnaires as $globalQuestionnaire) {
                DB::table('questionnaires')->updateOrInsert(
                    [
                        'questionnaire_id' => $globalQuestionnaire->id,
                        'global' => true,
                    ],
                    [
                        'title' => json_encode($globalQuestionnaire->title),
                        'description' => json_encode($globalQuestionnaire->description),
                        'therapist_id' => $globalQuestionnaire->therapist_id,
                        'questionnaire_id' => $globalQuestionnaire->id,
                        'global' => true,
                        'created_at' => Carbon::parse($globalQuestionnaire->created_at ?? now()),
                        'updated_at' => Carbon::now(),
                        'deleted_at' => $globalQuestionnaire->deleted_at ? Carbon::parse($globalQuestionnaire->deleted_at) : null,
                    ]
                );
                $newQuestionnaire = Questionnaire::withTrashed()->where('questionnaire_id', $globalQuestionnaire->id)->where('global', true)->first();
                $questions = GlobalDataSyncHelper::fetchData('get-questionnaire-questions', ['questionnaire_id' => $globalQuestionnaire->id]);
                if (!empty($questions)) {
                    foreach ($questions as $question) {
                        $file = GlobalDataSyncHelper::fetchData('get-question-file', ['question_id' => $question->id]);
                        $record = null;
                        if (!empty($file)) {
                            $file_url = env('GLOBAL_ADMIN_SERVICE_URL') . '/file/' . $file->id;
                            $file_path = File::QUESTIONNAIRE_PATH . '/' . $file->filename;

                            try {
                                $file_content = file_get_contents($file_url);
                                $record = File::create([
                                    'filename' => $file->filename,
                                    'path' => $file_path,
                                    'content_type' => $file->content_type,
                                ]);

                                // Save file to storage.
                                Storage::put($file_path, $file_content);
                            } catch (\Exception $e) {
                                Log::debug($e->getMessage());
                            }
                        }
                        // Add questions.
                        DB::table('questions')->updateOrInsert(
                            [
                                'question_id' => $question->id,
                                'questionnaire_id' => $newQuestionnaire->id,
                            ],
                            [
                                'title' => json_encode($question->title),
                                'type' => $question->type,
                                'questionnaire_id' => $newQuestionnaire->id,
                                'file_id' => $record ? $record->id : null,
                                'order' => $question->order,
                                'question_id' => $question->id,
                                'created_at' => Carbon::parse($question->created_at ?? now()),
                                'updated_at' => Carbon::now(),
                             ]
                        );
                        // Add answers.
                        $newQuestion = Question::where('questionnaire_id', $newQuestionnaire->id)->where('question_id', $question->id)->first();
                        $answers = GlobalDataSyncHelper::fetchData('get-question-answers', ['question_id' => $question->id]);
                        if (!empty($answers)) {
                            foreach ($answers as $answer) {
                                DB::table('answers')->updateOrInsert(
                                    [
                                        'answer_id' => $answer->id,
                                        'question_id' => $newQuestion->id,
                                    ],
                                    [
                                        'description' => json_encode($answer->description),
                                        'question_id' => $newQuestion->id,
                                        'answer_id' => $answer->id,
                                        'created_at' => Carbon::parse($answer->created_at ?? now()),
                                        'updated_at' => Carbon::now(),
                                    ]
                                );
                            }
                        }
                    }
                }
                $this->output->progressAdvance();
            }
            $this->output->progressFinish();

            // Re-enable activity logging after data sync
            Activity::enableLogging();
        }
        $this->info('Questionnaire data has been sync successfully');
    }
}
