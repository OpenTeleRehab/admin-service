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

        if (StaticPage::count() > 0) {
            $this->info('These static pages is already exists');
            return false;
        }

        foreach ([$admin_static_pages, $therapist_static_pages, $patient_static_pages] as $static_page) {
            $data = $static_page['data'];

            if (!empty($data)) {
                $file_content = file_get_contents('https://dummyimage.com/1200x800/00067c/fff');
                $file_path = File::STATIC_PAGE_PATH . '/dummy-image.png';

                $file = File::create([
                    'filename' => 'dummy-image.png',
                    'path' => $file_path,
                    'content_type' => 'image/png',
                ]);

                // Save file to storage.
                Storage::put($file_path, $file_content);

                // Store static page
                StaticPage::create([
                    'title' => $data['title'],
                    'content' => '<table style="border-collapse: collapse; width: 100%; height: 10px;" border="1"><tbody><tr style="height: 64px;"><td style="width: 100%; border-style: hidden; text-align: center; height: 10px;"><h6 style="text-align: center;"><strong>OpenTeleRehab is an open source multidisciplinary telerehabilitation software, connecting rehabilitation professionals with users in order to provide remote rehabilitation services.</strong></h6><p style="text-align: center;">Lorem ipsum dolor sit amet, consectetur adipiscing elit. Vivamus ullamcorper ultrices elit ut hendrerit. Sed molestie sed magna ac pharetra. Vivamus convallis diam in commodo ultricies.</p><p style="text-align: center;">Maecenas malesuada mauris neque. Nullam id molestie dolor, a facilisis velit. Nulla euismod arcu lacus, id mattis dui faucibus quis. In vitae nulla consectetur, suscipit tortor sit amet, sollicitudin arcu.</p><p style="text-align: center;">Morbi vel molestie lectus, id aliquam risus. Vivamus vel ex quis diam interdum tempus. Praesent id fermentum tellus, et vulputate metus. Pellentesque id pellentesque ante.</p><p style="text-align: center;">Open Rehab is powered by</p></td></tr></tbody></table><table style="border-collapse: collapse; width: 100%;" border="1"><tbody><tr><td style="width: 100%; border-style: hidden;"><img class="n3VNCb" style="width: 138px; height: 59px; margin: 0px auto; display: block;" src="https://inclusivefutures.org/wp-content/uploads/2020/05/HumanityInclusion2.svg" alt="Humanity &amp; Inclusion | Inclusive Futures" data-noaft="1" /></td></tr></tbody></table>',
                    'file_id' => $data['file'] !== null ? $file->id : null,
                    'platform' => $data['platform'],
                    'url_path_segment' => $data['url'],
                    'private' => $data['private'],
                    'background_color' => $data['background_color'],
                    'text_color' => $data['text_color'],
                ]);
            }
        }

        $this->info('Static page has been created successfully');

        return 0;
    }
}
