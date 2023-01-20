<?php

namespace App\Console\Commands;

use App\Helpers\FileHelper;
use App\Models\Exercise;
use App\Models\File;
use App\Models\Forwarder;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class SyncExerciseData extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'hi:sync-exercise-data';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Sync exercises data from global to other organization';

    /**
     * @return void
     * @throws \Illuminate\Http\Client\RequestException
     */
    public function handle()
    {
        if (env('APP_NAME') != 'hi') {
            // Sync exercise data.
            $access_token = Forwarder::getAccessToken(Forwarder::GADMIN_SERVICE);
            $globalExercises = json_decode(Http::withToken($access_token)->get(env('GLOBAL_ADMIN_SERVICE_URL') . '/get-exercises'));
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
                    ]
                );
                $newExercise = Exercise::withTrashed()->where('exercise_id', $globalExercise->id)->where('global', true)->first();

                // Add files.
                $files = json_decode(Http::withToken($access_token)->get(env('GLOBAL_ADMIN_SERVICE_URL') . '/get-exercise-files', ['exercise_id' => $globalExercise->id]));

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
                                $thumbnailFilePath = FileHelper::generateThumbnail($record, File::EXERCISE_THUMBNAIL_PATH);

                                if ($thumbnailFilePath) {
                                    $record->update(['thumbnail' => $thumbnailFilePath]);
                                }

                                // Add to exercise file.
                                DB::table('exercise_file')->insert(
                                    [
                                        'exercise_id' => $newExercise->id,
                                        'file_id' => $record->id,
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
        }
        $this->info('Exercise data has been sync successfully');
    }
}
