<?php

namespace App\Console\Commands;

use App\Models\File;
use App\Models\StaticPage;
use App\Models\Translation;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;

class ImportStaticPage extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'hi:import-static-page';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Import static page';

    /**
     * The console command example helper.
     *
     * @var string
     */
    protected $help = 'php artisan hi:import-static-page';

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle()
    {
        $admin_static_pages = Http::get(env('GLOBAL_ADMIN_SERVICE_URL') . '/page/static-page-data/?url-segment=about-us&platform=' . Translation::ADMIN_PORTAL);
        $therapist_static_pages = Http::get(env('GLOBAL_ADMIN_SERVICE_URL') . '/page/static-page-data/?url-segment=about-us&platform=' . Translation::THERAPIST_PORTAL);
        $patient_static_pages = Http::get(env('GLOBAL_ADMIN_SERVICE_URL') . '/page/static-page-data/?url-segment=about-us&platform=' . Translation::PATIENT_APP);

        foreach ([$admin_static_pages, $therapist_static_pages, $patient_static_pages] as $static_page) {
            $data = $static_page['data'];

            if ($data['file'] !== null) {
                $file_url = env('GLOBAL_ADMIN_SERVICE_URL') . '/file/' . $data['file_id'];
                $file_content = file_get_contents($file_url);
                $file_path = File::STATIC_PAGE_PATH . '/' . $data['file']['fileName'];

                $file = File::create([
                    'filename' => $data['file']['fileName'],
                    'path' => $file_path,
                    'content_type' => $data['file']['fileType'],
                ]);

                // Save file to storage.
                Storage::put($file_path, $file_content);
            }

            StaticPage::create([
                'title' => $data['title'],
                'content' => $data['content'],
                'file_id' => $data['file'] !== null ? $file->id : null,
                'platform' => $data['platform'],
                'url_path_segment' => $data['url'],
                'private' => $data['private'],
                'background_color' => $data['background_color'],
                'text_color' => $data['text_color'],
            ]);
        }

        $this->info('Static page has been created successfully');

        return 0;
    }
}
