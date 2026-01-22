<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Helpers\GlobalDataSyncHelper;
use App\Models\File;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Spatie\Activitylog\Facades\Activity;
use App\Models\StaticPage;
use Carbon\Carbon;

class SyncFaqData extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'hi:sync-faq-data';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Sync faq data from global to other organization';

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

        $this->alert('Starting faq sync...');

        // Fetch faq data from global
        $globalFaqs = GlobalDataSyncHelper::fetchData('get-faq-pages');
        if (!$globalFaqs) {
            $this->error('Failed to fetch faqs from global.');
            return;
        }

        $this->output->progressStart(count($globalFaqs));
        foreach ($globalFaqs as $globalFaq) {
            // Get existing local faq & delete old files
            $existingFaq = json_decode(StaticPage::where('global_id', $globalFaq->id)->first());
            if ($existingFaq) {
                $existingContent = json_decode(json_encode($existingFaq->content), true) ?? [];
                // Extract old content file IDs
                $oldFileIds = self::extractFileIdsFromContent($existingContent);
                // Delete old content files
                self::deleteFilesByIds($oldFileIds);

                // Delete old associated file
                if ($existingFaq->file_id) {
                    self::deleteFilesByIds([$existingFaq->file_id]);
                }
            }
            // Map and store files from global content
            $decodedContent = json_decode(json_encode($globalFaq->content), true);
            $newContent = self::mapContentFiles($decodedContent);
            $fileId = null;
            if ($globalFaq->file) {
                // Fetch and store the associated file
                $file = $globalFaq->file;
                try {
                    $file_url = env('GLOBAL_ADMIN_SERVICE_URL') . '/file/' . $file->id;
                    $filePath = File::STATIC_PAGE_PATH . '/' . $file->filename;
                    $fileContent = file_get_contents($file_url);
                    $localFile = File::create([
                        'filename' => $file->filename,
                        'path' => $filePath,
                        'content_type' => $file->content_type,
                    ]);

                    Storage::put($filePath, $fileContent);
                    $fileId = $localFile->id;
                } catch (\Exception $e) {
                    Log::debug("Failed to store associated file from global {$file->id}: " . $e->getMessage());
                }
            }

            // upsert faq
            DB::table('static_pages')->updateOrInsert(
                ['global_id' => $globalFaq->id],
                [
                    'title' => json_encode($globalFaq->title, JSON_UNESCAPED_UNICODE),
                    'content' => json_encode($newContent, JSON_UNESCAPED_UNICODE),
                    'file_id' => $fileId,
                    'platform' => $globalFaq->platform,
                    'url_path_segment' => $globalFaq->url_path_segment,
                    'private' => $globalFaq->private,
                    'background_color' => $globalFaq->background_color,
                    'text_color' => $globalFaq->text_color,
                    'auto_translated' => json_encode($globalFaq->auto_translated),
                    'created_at' => Carbon::parse($globalFaq->created_at ?? now()),
                    'updated_at' => Carbon::now(),
                ]
            );

            $this->output->progressAdvance();
        }

        $this->output->progressFinish();

        // Re-enable activity logging after data sync
        Activity::enableLogging();

        $this->info('Faqs sync completed successfully!');
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
            ? GlobalDataSyncHelper::fetchData('get-faq-content-files', ['file_ids' => $allGlobalFileIds])
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
