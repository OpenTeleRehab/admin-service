<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Helpers\GlobalDataSyncHelper;
use App\Models\File;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Spatie\Activitylog\Facades\Activity;
use App\Models\Guidance;
use Carbon\Carbon;

class SyncTutorialData extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'hi:sync-tutorial-data';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Sync tutorial data from global to other organization';

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

        $this->alert('Starting tutorial sync...');

        // Fetch tutorials from global
        $globalTutorials = GlobalDataSyncHelper::fetchData('get-tutorials');
        if (!$globalTutorials) {
            $this->error('Failed to fetch tutorials from global.');
            return;
        }
        
        $globalTutorialIds = collect($globalTutorials)->pluck('id')->toArray();
        $this->output->progressStart(count($globalTutorials));
        foreach ($globalTutorials as $globalTutorial) {
            // Get existing guidance
            $existingGuidance = json_decode(Guidance::find($globalTutorial->id));
            if ($existingGuidance) {
                $existingContent = json_decode(json_encode($existingGuidance->content), true) ?? [];
                // Extract old file IDs
                $oldFileIds = self::extractFileIdsFromContent($existingContent);
                // Delete old files
                self::deleteFilesByIds($oldFileIds);
            }
            $decodedContent = json_decode(json_encode($globalTutorial->content), true);
            $newContent = self::mapContentFiles($decodedContent);

            // Upsert tutorial
            DB::table('guidances')->updateOrInsert(
                ['id' => $globalTutorial->id],
                [
                    'content' => json_encode($newContent, JSON_UNESCAPED_UNICODE),
                    'order' => $globalTutorial->order,
                    'title' => json_encode($globalTutorial->title, JSON_UNESCAPED_UNICODE),
                    'auto_translated' => json_encode($globalTutorial->auto_translated),
                    'target_role' => $globalTutorial->target_role,
                    'created_at' => Carbon::now(),
                    'updated_at' => Carbon::now(),
                ]
            );

            $this->output->progressAdvance();
        }

        // Delete tutorials that no longer exist in the global tutorials
        Guidance::whereNotIn('id', $globalTutorialIds)
            ->delete();
        $this->output->progressFinish();

        $this->info('Tutorial sync completed successfully!');
    }

    /**
     * Map and store files from global content.
     * @param array $content
     * @return array
     */
    private static function mapContentFiles(array $content)
    {
        $localFileIds = [];
        $allGlobalFileIds = [];

        // Collect all file ids from all content
        $allGlobalFileIds = self::extractFileIdsFromContent($content);

        // Fetch all files from global
        $globalFiles = !empty($allGlobalFileIds)
            ? GlobalDataSyncHelper::fetchData('get-tutorial-files', ['file_ids' => $allGlobalFileIds])
            : [];
        $globalFilesById = collect($globalFiles)->keyBy('id');

        // Store each file from global and map to local file ID
        foreach ($allGlobalFileIds as $globalFileId) {
            $globalFile = $globalFilesById[$globalFileId] ?? null;
            if (!$globalFile) continue;

            try {
                $fileUrl = env('GLOBAL_ADMIN_SERVICE_URL') . '/file/' . $globalFile->id;
                $filePath = File::FILE_PATH . '/' . $globalFile->filename;

                $fileContent = file_get_contents($fileUrl);

                $localFile = File::create([
                    'filename' => $globalFile->filename,
                    'path' => $filePath,
                    'content_type' => $globalFile->content_type,
                ]);

                Storage::put($filePath, $fileContent);

                // Map global file ID to local file ID
                $localFileIds[$globalFileId] = $localFile->id;
            } catch (\Exception $e) {
                Log::debug("Failed to store file from global {$globalFileId}: " . $e->getMessage());
            }
        }

        // Replace all global file IDs in all language content with new stored local file IDs
        foreach ($content as $lang => $html) {
            if (!is_string($html)) continue;

            $content[$lang] = preg_replace_callback(
                '#(/file/)(\d+)#',
                function ($matches) use ($localFileIds) {
                    $globalFileId = (int)$matches[2];
                    $localFileId = $localFileIds[$globalFileId] ?? $globalFileId;

                    return '/file/' . $localFileId;
                },
                $html
            );
        }

        return $content;
    }

    /**
     * Extract file IDs from translatable content.
     *
     * @param array $content
     * @return array
     */
    private static function extractFileIdsFromContent(array $content)
    {
        $fileIds = [];
        foreach ($content as $html) {
            if (!is_string($html)) {
                continue;
            }
            
            preg_match_all('#/file/(\d+)#', $html, $matches);
            if (!empty($matches[1])) {
                $fileIds = array_merge($fileIds, $matches[1]);
            }
        }

        return array_unique(array_map('intval', $fileIds));
    }

    /**
     * Delete files by their IDs.
     *
     * @param array $fileIds
     * @return void
     */
    private static function deleteFilesByIds(array $fileIds)
    {
        if (empty($fileIds)) {
            return;
        }

        $files = File::whereIn('id', $fileIds)->get();

        foreach ($files as $file) {
            Storage::delete($file->path);
            $file->delete();
        }
    }
}
