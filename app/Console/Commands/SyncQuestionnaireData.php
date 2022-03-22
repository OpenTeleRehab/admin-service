<?php

namespace App\Console\Commands;

use App\Models\File;
use App\Models\Question;
use App\Models\Questionnaire;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

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

            // Sync questionnaire data.
            $globalQuestionnaires = json_decode(Http::get(env('GLOBAL_ADMIN_SERVICE_URL') . '/get-questionnaires'));
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
                        'deleted_at' => $globalQuestionnaire->deleted_at ? Carbon::parse($globalQuestionnaire->deleted_at) : $globalQuestionnaire->deleted_at,
                    ]
                );
                $newQuestionnaire = Questionnaire::withTrashed()->where('questionnaire_id', $globalQuestionnaire->id)->where('global', true)->first();
                $questions = json_decode(Http::get(env('GLOBAL_ADMIN_SERVICE_URL') . '/get-questionnaire-questions', ['questionnaire_id' => $globalQuestionnaire->id]));
                if (!empty($questions)) {
                    foreach ($questions as $question) {
                        $file = json_decode(Http::get(env('GLOBAL_ADMIN_SERVICE_URL') . '/get-question-file', ['question_id' => $question->id]));
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
                             ]
                        );
                        // Add answers.
                        $newQuestion = Question::where('questionnaire_id', $newQuestionnaire->id)->where('question_id', $question->id)->first();
                        $answers = json_decode(Http::get(env('GLOBAL_ADMIN_SERVICE_URL') . '/get-question-answers', ['question_id' => $question->id]));
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
                                    ]
                                );
                            }
                        }
                    }
                }
            }
        }
        $this->info('Questionnaire data has been sync successfully');
    }
}
