<?php

namespace App\Http\Controllers;

use App\Helpers\FileHelper;
use App\Http\Resources\ExerciseResource;
use App\Models\Exercise;
use App\Models\File;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ExerciseController extends Controller
{
    /**
     * @param \Illuminate\Http\Request $request
     *
     * @return array
     */
    public function index(Request $request)
    {
        $query = Exercise::select();
        $filter = json_decode($request->get('filter'), true);

        if (isset($filter['search_value'])) {
            $query->where('title', 'like', '%' . $filter['search_value'] . '%');
        }
        $exercises = $query->paginate($request->get('page_size'));

        $info = [
            'current_page' => $exercises->currentPage(),
            'total_count' => $exercises->total(),
        ];
        return [
            'success' => true,
            'data' => ExerciseResource::collection($exercises),
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
        $exercise = Exercise::create([
            'title' => $request->get('title'),
            'include_feedback' => $request->boolean('include_feedback'),
            'get_pain_level' => $request->boolean('get_pain_level'),
            'additional_fields' => json_decode($request->get('additional_fields')),
        ]);

        // Upload files and attach to Exercise.
        $i = 0;
        $allFiles = $request->allFiles();
        foreach ($allFiles as $uploadedFile) {
            $file = FileHelper::createFile($uploadedFile, File::EXERCISE_PATH);
            if ($file) {
                $exercise->files()->attach($file->id, ['order' => ++$i]);
            }
        }

        if ($exercise) {
            return ['success' => true, 'message' => 'success_message.exercise_create'];
        }
        return ['success' => false, 'message' => 'error_message.exercise_create'];
    }

    /**
     * @param \App\Models\Exercise $exercise
     *
     * @return \App\Http\Resources\ExerciseResource
     */
    public function show(Exercise $exercise)
    {
        return new ExerciseResource($exercise);
    }

    /**
     * @param \Illuminate\Http\Request $request
     * @param \App\Models\Exercise $exercise
     *
     * @return array
     */
    public function update(Request $request, Exercise $exercise)
    {
        $exercise->update([
            'title' => $request->get('title'),
            'include_feedback' => $request->boolean('include_feedback'),
            'get_pain_level' => $request->boolean('get_pain_level'),
            'additional_fields' => json_decode($request->get('additional_fields')),
        ]);

        // Remove files.
        $exerciseFileIDs = $exercise->files()->pluck('id')->toArray();
        $mediaFileIDs = $request->get('media_files', []);
        $removeFileIDs = array_diff($exerciseFileIDs, $mediaFileIDs);
        foreach ($removeFileIDs as $removeFileID) {
            $removeFile = File::find($removeFileID);
            FileHelper::removeFile($removeFile);
        }

        // Update ordering.
        foreach ($mediaFileIDs as $index => $mediaFileID) {
            DB::table('exercise_file')
                ->where('exercise_id', $exercise->id)
                ->where('file_id', $mediaFileID)
                ->update(['order' => $index]);
        }

        // Upload files and attach to Exercise.
        $allFiles = $request->allFiles();
        foreach ($allFiles as $index => $uploadedFile) {
            $file = FileHelper::createFile($uploadedFile, File::EXERCISE_PATH);
            if ($file) {
                $exercise->files()->attach($file->id, ['order' => (int) $index]);
            }
        }

        return ['success' => true, 'message' => 'success_message.exercise_update'];
    }

    /**
     * @param \App\Models\Exercise $exercise
     *
     * @return array
     * @throws \Exception
     */
    public function destroy(Exercise $exercise)
    {
        if ($exercise->canDelete()) {
            // Todo: delete media resources.
            $exercise->delete();
            return ['success' => true, 'message' => 'success_message.exercise_delete'];
        }
        return ['success' => false, 'message' => 'error_message.exercise_delete'];
    }

    /**
     * @param \Illuminate\Http\Request $request
     *
     * @return \Illuminate\Http\Resources\Json\AnonymousResourceCollection
     */
    public function getByIds(Request $request)
    {
        $exerciseIds = $request->get('exercise_ids', []);
        $exercises = Exercise::whereIn('id', $exerciseIds)->get();
        return ExerciseResource::collection($exercises);
    }
}