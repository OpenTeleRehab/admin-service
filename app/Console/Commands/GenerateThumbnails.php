<?php

namespace App\Console\Commands;

use App\Helpers\FileHelper;
use App\Models\File;
use Illuminate\Console\Command;
use Symfony\Component\HttpFoundation\File\Exception\FileException;

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
     * @return true
     * @throws \Spatie\PdfToImage\Exceptions\PdfDoesNotExist
     */
    public function handle()
    {

        $files = File::where('content_type', 'LIKE', 'image%')
            ->where('content_type', '<>', 'image/svg')
            ->where('thumbnail', null)
            ->get();

        if ($files->count() === 0) {
            $this->info('There is not file to generate');
            return true;
        }

        $this->alert('Start generating thumbnails: ' . $files->count());
        $this->output->progressStart($files->count());
        foreach ($files as $file) {
            $thumbnailFilePath = File::EXERCISE_THUMBNAIL_PATH;
            if (str_starts_with($file->path, File::EDUCATION_MATERIAL_PATH)) {
                $thumbnailFilePath = File::EDUCATION_MATERIAL_PATH;
            } elseif (str_starts_with($file->path, File::QUESTIONNAIRE_PATH)) {
                $thumbnailFilePath = File::QUESTIONNAIRE_PATH;
            }

            $thumbnailFile = FileHelper::generateThumbnail($file, $thumbnailFilePath);

            if ($thumbnailFile) {
                $file->update(['thumbnail' => $thumbnailFile]);
            }
            $this->output->progressAdvance();
        }

        $this->output->progressFinish();

        return true;
    }
}
