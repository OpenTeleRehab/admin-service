<?php

namespace App\Console\Commands;

use App\Helpers\FileHelper;
use App\Models\EducationMaterial;
use App\Models\File;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class SyncEducationMaterialData extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'hi:sync-education-material-data';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Sync materials data from global to other organization';

    /**
     * @return void
     * @throws \Illuminate\Http\Client\RequestException
     */
    public function handle()
    {
        if (env('APP_NAME') != 'hi') {
            // Sync eduction material data.
            $access_token = Forwarder::getAccessToken(Forwarder::GADMIN_SERVICE);
            $globalEducationMaterials = json_decode(Http::withToken($access_token)->get(env('GLOBAL_ADMIN_SERVICE_URL') . '/get-education-materials'));
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
                $files = json_decode(Http::withToken($access_token)->get(env('GLOBAL_ADMIN_SERVICE_URL') . '/get-education-material-files', ['file_ids' => $filesIDs]));
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
                                $thumbnailFilePath = FileHelper::generateThumbnail($record, File::EDUCATION_MATERIAL_THUMBNAIL_PATH);

                                if ($thumbnailFilePath) {
                                    $record->update(['thumbnail' => $thumbnailFilePath]);
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
        }
        $this->info('Education material data has been sync successfully');
    }
}
