<?php

namespace App\Console\Commands;

use App\Helpers\FileHelper;
use App\Models\Answer;
use App\Models\EducationMaterial;
use App\Models\Exercise;

use App\Models\File;
use App\Models\Question;
use App\Models\Questionnaire;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class SyncLibraryData extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'hi:sync-library-data';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Sync exercises, materials and questionnaires data from global to other organization';

    /**
     * @return void
     * @throws \Illuminate\Http\Client\RequestException
     */
    public function handle()
    {
        if (env('APP_NAME') != 'hi') {
            // Sync exercise data
            $globalExercises = json_decode(Http::get(env('GLOBAL_ADMIN_SERVICE_URL') . '/get-exercises'));
            // Remove existing global data before import.
            $exercises = Exercise::withTrashed()->where('global', true)->get();
            if ($exercises) {
                foreach ($exercises as $exercise) {
                    // Remove files.
                    $removeFileIDs = $exercise->files()->pluck('id')->toArray();
                    foreach ($removeFileIDs as $removeFileID) {
                        $removeFile = File::find($removeFileID);
                        $removeFile->delete();
                    }
                    // Remove exercise file in exercise file table.
                    DB::table('exercise_file')->where('exercise_id', $exercise->id)->delete();
                }
            }

            // Import global exercises to org.
            foreach ($globalExercises as $globalExercise) {
                DB::table('exercises')->updateOrInsert(
                    [
                        'exercise_id' => $globalExercise->id,
                        'global' => true,
                    ],
                    [
                        'title' => json_encode($globalExercise->title),
                        'sets' => $globalExercise->sets,
                        'reps' => $globalExercise->reps,
                        'include_feedback' => $globalExercise->include_feedback,
                        'get_pain_level' => $globalExercise->get_pain_level,
                        'therapist_id' => $globalExercise->therapist_id,
                        'exercise_id' => $globalExercise->id,
                        'global' => true,
                        'deleted_at' => $globalExercise->deleted_at ? Carbon::parse($globalExercise->deleted_at) : $globalExercise->deleted_at,
                    ],
                );
                $newExercise = Exercise::withTrashed()->where('exercise_id', $globalExercise->id)->where('global', true)->first();
                // Add files.
                $files = json_decode(Http::get(env('GLOBAL_ADMIN_SERVICE_URL') . '/get-exercise-files', ['exercise_id' => $globalExercise->id]));
                if (!empty($files)) {
                    $index = 0;
                    foreach ($files as $file) {
                        $file_url = env('GLOBAL_ADMIN_SERVICE_URL') . '/file/' . $file->id;
                        $file_path = File::EXERCISE_PATH . '/' . $file->filename;

                        try {
                            $file_content = file_get_contents($file_url);
                            $record = File::create([
                                'filename' => $file->filename,
                                'path' => $file_path,
                                'content_type' => $file->content_type,
                            ]);

                            // Save file to storage.
                            Storage::put($file_path, $file_content);
                            if ($record) {
                                if ($file->content_type === 'video/mp4') {
                                    $thumbnailFilePath = FileHelper::generateVideoThumbnail($record->id, $file_path, File::EXERCISE_THUMBNAIL_PATH);

                                    if ($thumbnailFilePath) {
                                        $record->update([
                                            'thumbnail' => $thumbnailFilePath,
                                        ]);
                                    }
                                }

                                if ($file->content_type === 'application/pdf') {
                                    $thumbnailFilePath = FileHelper::generatePdfThumbnail($record->id, $file_path, File::EXERCISE_THUMBNAIL_PATH);

                                    if ($thumbnailFilePath) {
                                        $record->update([
                                            'thumbnail' => $thumbnailFilePath,
                                        ]);
                                    }
                                }
                                // Add to exercise file
                                DB::table('exercise_file')->insert(
                                    [
                                        'exercise_id' => $newExercise->id,
                                        'file_id' =>$record->id,
                                        'order' => $index,
                                    ]
                                );
                            }
                            $index++;
                        } catch (\Exception $e) {
                            Log::debug($e->getMessage());
                        }
                    }
                }
            }

            // Sync eduction material data.
            $globalEducationMaterials = json_decode(Http::get(env('GLOBAL_ADMIN_SERVICE_URL') . '/get-education-materials'));
            $educationMaterials = DB::table('education_materials')->where('global', true)->get();
            // Remove data before import.
            if ($educationMaterials) {
                foreach ($educationMaterials as $educationMaterial) {
                    $fileIDs = array_values(get_object_vars(json_decode($educationMaterial->file_id)));
                    File::whereIn('id', $fileIDs)->delete();
                }
            }
            // Import global material to org.
            foreach ($globalEducationMaterials as $globalEducationMaterial) {
                 DB::table('education_materials')->updateOrInsert(
                     [
                         'education_material_id' => $globalEducationMaterial->id,
                         'global' => true,
                     ],
                    [
                        'title' => json_encode($globalEducationMaterial->title),
                        'file_id' => json_encode($globalEducationMaterial->file_id),
                        'therapist_id' => $globalEducationMaterial->therapist_id,
                        'education_material_id' => $globalEducationMaterial->id,
                        'global' => true,
                        'deleted_at' => $globalEducationMaterial->deleted_at ? Carbon::parse($globalEducationMaterial->deleted_at) : $globalEducationMaterial->deleted_at,
                    ]
                );
                $filesIDs = array_values(get_object_vars($globalEducationMaterial->file_id));
                $files = json_decode(Http::get(env('GLOBAL_ADMIN_SERVICE_URL') . '/get-education-material-files', ['file_ids' => $filesIDs]));
                $newFileIDs = $globalEducationMaterial->file_id;
                if (!empty($files)) {
                    foreach ($files as $file) {
                        $file_url = env('GLOBAL_ADMIN_SERVICE_URL') . '/file/' . $file->id;
                        $file_path = File::EDUCATION_MATERIAL_PATH . '/' . $file->filename;

                        try {
                            $file_content = file_get_contents($file_url);

                            $record = File::create([
                                'filename' => $file->filename,
                                'path' => $file_path,
                                'content_type' => $file->content_type,
                            ]);

                            // Save file to storage.
                            Storage::put($file_path, $file_content);
                            if ($record) {
                                if ($file->content_type === 'video/mp4') {
                                    $thumbnailFilePath = FileHelper::generateVideoThumbnail($record->id, $file_path,
                                        File::EDUCATION_MATERIAL_THUMBNAIL_PATH);

                                    if ($thumbnailFilePath) {
                                        $record->update([
                                            'thumbnail' => $thumbnailFilePath,
                                        ]);
                                    }
                                }

                                if ($file->content_type === 'application/pdf') {
                                    $thumbnailFilePath = FileHelper::generatePdfThumbnail($record->id, $file_path,
                                        File::EDUCATION_MATERIAL_THUMBNAIL_PATH);

                                    if ($thumbnailFilePath) {
                                        $record->update([
                                            'thumbnail' => $thumbnailFilePath,
                                        ]);
                                    }
                                }
                                // Update file id with new created id.
                                foreach ($newFileIDs as $key => $value) {
                                    if ($file->id == $value) {
                                        $newFileIDs->$key = $record->id;
                                    }
                                }
                            }
                        } catch (\Exception $e) {
                            Log::debug($e->getMessage());
                        }
                    }
                    // Update material file id.
                    $education = EducationMaterial::withTrashed()->where('education_material_id', $globalEducationMaterial->id)->where('global', true)->first();
                    DB::table('education_materials')->where('id', $education->id)->update(['file_id' => json_encode($newFileIDs)]);
                }
            }

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
        $this->info('Library data has been sync successfully');
    }
}
