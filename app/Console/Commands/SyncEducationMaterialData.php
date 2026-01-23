<?php

namespace App\Console\Commands;

use App\Helpers\FileHelper;
use App\Helpers\GlobalDataSyncHelper;
use App\Models\EducationMaterial;
use App\Models\File;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Spatie\Activitylog\Facades\Activity;

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
            // Disable activity logging for data sync
            Activity::disableLogging();

            $this->alert('Starting education material sync...');

            // Sync eduction material data.
            $globalEducationMaterials = GlobalDataSyncHelper::fetchData('get-education-materials');
            if (!$globalEducationMaterials) {
                $this->error('Failed to fetch education materials from global.');
                return;
            }
            $this->output->progressStart(count($globalEducationMaterials));
            $educationMaterials = DB::table('education_materials')->where('global', true)->get();
            // Remove data before import.
            if ($educationMaterials) {
                foreach ($educationMaterials as $educationMaterial) {
                    $fileIDs = array_values(get_object_vars(json_decode($educationMaterial->file_id)));
                    $files = File::whereIn('id', $fileIDs)->get(['id', 'path']);
                    Storage::delete($files->pluck('path')->toArray());
                    File::whereIn('id', $files->pluck('id'))->delete();
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
                        'created_at' => Carbon::parse($globalEducationMaterial->created_at ?? now()),
                        'updated_at' => Carbon::now(),
                        'deleted_at' => $globalEducationMaterial->deleted_at ? Carbon::parse($globalEducationMaterial->deleted_at) : null,
                    ]
                );
                $filesIDs = array_values(get_object_vars($globalEducationMaterial->file_id));
                $files = GlobalDataSyncHelper::fetchData('get-education-material-files', ['file_ids' => $filesIDs]);
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
                $this->output->progressAdvance();
            }
            $this->output->progressFinish();

            // Re-enable activity logging after data sync
            Activity::enableLogging();
        }
        $this->info('Education material data has been sync successfully');
    }
}
