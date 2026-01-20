<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Helpers\GlobalDataSyncHelper;
use App\Models\AssistiveTechnology;
use App\Models\File;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;
use Spatie\Activitylog\Facades\Activity;

class SyncAssistiveProductData extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'hi:sync-assistive-product-data';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Sync assistive product data from global to other organization';

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

        $this->alert('Starting assistive product sync...');

        // Fetch assistive products from global
        $globalAssistiveProducts = GlobalDataSyncHelper::fetchData('get-assistive-products');
        if (!$globalAssistiveProducts) {
            $this->error('Failed to fetch assistive products from global.');
            return;
        }

        $this->output->progressStart(count($globalAssistiveProducts));
        foreach ($globalAssistiveProducts as $globalAssistiveProduct) {
            $fileId = null;
            // Get file data and store if exists
            if ($globalAssistiveProduct->file) {
                // Remove existing file if any
                $existingAssistiveProduct = AssistiveTechnology::withTrashed()->find($globalAssistiveProduct->id);
                if ($existingAssistiveProduct && $existingAssistiveProduct->file) {
                    $existingFile = $existingAssistiveProduct->file;
                    if ($existingFile) {
                        Storage::delete($existingFile->path);
                        $existingFile->delete();
                    }
                }
                // Store new file
                $file = $globalAssistiveProduct->file;
                $file_url = env('GLOBAL_ADMIN_SERVICE_URL') . '/file/' . $file->id;
                $file_path = File::ASSISTIVE_TECHNOLOGY_PATH . '/' . $file->filename;

                try {
                    $file_content = file_get_contents($file_url);
                    $record = File::create([
                        'filename' => $file->filename,
                        'path' => $file_path,
                        'content_type' => $file->content_type,
                    ]);

                    // Save file to storage.
                    Storage::put($file_path, $file_content);
                    $fileId = $record->id;
                } catch (\Exception $e) {
                    Log::debug($e->getMessage());
                }
            }
            // Upsert assistive product
            DB::table('assistive_technologies')->updateOrInsert(
                ['id' => $globalAssistiveProduct->id],
                [
                    'code' => $globalAssistiveProduct->code,
                    'name' => json_encode($globalAssistiveProduct->name),
                    'description' => json_encode($globalAssistiveProduct->description),
                    'file_id' => $fileId,
                    'auto_translated' => json_encode($globalAssistiveProduct->auto_translated),
                    'created_at' => $globalAssistiveProduct->created_at ? Carbon::parse($globalAssistiveProduct->created_at) : $globalAssistiveProduct->created_at,
                    'updated_at' => $globalAssistiveProduct->updated_at ? Carbon::parse($globalAssistiveProduct->updated_at) : $globalAssistiveProduct->updated_at,
                    'deleted_at' => $globalAssistiveProduct->deleted_at ? Carbon::parse($globalAssistiveProduct->deleted_at) : $globalAssistiveProduct->deleted_at,
                ]
            );
            $this->output->progressAdvance();
        }

        $this->output->progressFinish();

        $this->info('Assistive product sync completed successfully!');
}
}
