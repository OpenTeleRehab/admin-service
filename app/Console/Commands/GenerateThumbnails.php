<?php

namespace App\Console\Commands;

use App\Helpers\FileHelper;
use App\Models\File;
use Illuminate\Console\Command;

class GenerateThumbnails extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'hi:generate-thumbnails';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Generate thumbnails';

    /**
     * The console command example helper.
     *
     * @var string
     */
    protected $help = 'php artisan hi:generate-thumbnails';

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle()
    {

        $files = File::where('content_type', 'LIKE', 'image%')
            ->where('thumbnail', null)
            ->get();

        $this->alert('Start generating thumbnails: ' . count($files));
        $this->output->progressStart(count($files));
        foreach ($files as $file) {
            $thumbnailFile = FileHelper::generateThumbnail($file, File::EXERCISE_THUMBNAIL_PATH);

            if ($thumbnailFile) {
                $file->update(['thumbnail' => $thumbnailFile]);
            }
            $this->output->progressAdvance();
        }

        $this->output->progressFinish();

        return 0;
    }
}
