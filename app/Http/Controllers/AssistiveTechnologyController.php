<?php

namespace App\Http\Controllers;

use App\Events\ApplyAssistiveTechnologyAutoTranslationEvent;
use App\Helpers\FileHelper;
use App\Http\Resources\AssistiveTechnologyResource;
use App\Models\AssistiveTechnology;
use App\Models\File;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class AssistiveTechnologyController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return array
     */
    public function index()
    {
        return ['success' => true, 'data' => AssistiveTechnologyResource::collection(AssistiveTechnology::all())];
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     *
     * @return array
     */
    public function store(Request $request)
    {
        $existing = AssistiveTechnology::where('code', $request->get('code'))->count();

        if ($existing) {
            return abort(409, 'error_message.assistive_technology_exists');
        }

        if ($request->hasFile('file')) {
            $file = FileHelper::createFile($request->file('file'), File::ASSISTIVE_TECHNOLOGY_PATH);
        }

        $assistive_technology = AssistiveTechnology::create([
            'code' => $request->get('code'),
            'name' => $request->get('name'),
            'description' => $request->get('description'),
            'file_id' => $file->id
        ]);

        // Add automatic translation for Assistive Technology.
        event(new ApplyAssistiveTechnologyAutoTranslationEvent($assistive_technology));

        return ['success' => true, 'message' => 'success_message.assistive_technology_add'];
    }

    /**
     * Display the specified resource.
     *
     * @param  \App\Models\AssistiveTechnology  $assistiveTechnology
     *
     * @return \App\Http\Resources\AssistiveTechnologyResource
     */
    public function show(AssistiveTechnology $assistiveTechnology)
    {
        return new AssistiveTechnologyResource($assistiveTechnology);
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \App\Models\AssistiveTechnology  $assistiveTechnology
     *
     * @return array
     */
    public function update(Request $request, AssistiveTechnology $assistiveTechnology)
    {
        $existing = AssistiveTechnology::where('id', '<>', $assistiveTechnology->id)
            ->where('code', $request->get('code'))
            ->count();

        if ($existing) {
            return abort(409, 'error_message.assistive_technology_exists');
        }

        // Replace new file.
        $file_id = $assistiveTechnology->file_id;

        if ($request->hasFile('file')) {
            $old_file = File::find($assistiveTechnology->file_id);

            if ($old_file) {
                $old_file->delete();
            }

            $file = FileHelper::createFile($request->file('file'), File::ASSISTIVE_TECHNOLOGY_PATH);

            $file_id = $file->id;
        }

        $assistiveTechnology->update([
            'code' => $request->get('code'),
            'name' => $request->get('name'),
            'description' => $request->get('description'),
            'file_id' => $file_id,
            'auto_translated' => false,
        ]);

        return ['success' => true, 'message' => 'success_message.assistive_technology_update'];
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  \App\Models\AssistiveTechnology  $assistiveTechnology
     *
     * @return array
     */
    public function destroy(AssistiveTechnology $assistiveTechnology)
    {
        $assistiveTechnology->delete();

        return ['success' => true, 'message' => 'success_message.assistive_technology_delete'];
    }
}
