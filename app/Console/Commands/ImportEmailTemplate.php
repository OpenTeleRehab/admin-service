<?php

namespace App\Console\Commands;

use App\Models\EmailTemplate;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class ImportEmailTemplate extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'hi:import-email-template';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Import email templates';

    /**
     * The console command example helper.
     *
     * @var string
     */
    protected $help = 'php artisan hi:import-email-template';

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle()
    {
        $templates = Storage::get('email_template/content.json');
        $templates = json_decode($templates, true);

        $this->output->progressStart(count($templates));

        foreach ($templates as $template) {
            $prefix = Str::slug($template['content_type']);

            $exist = EmailTemplate::where('prefix', $prefix)->exists();

            if (!$exist) {
                EmailTemplate::create([
                    'prefix' => $prefix,
                    'content_type' => $template['content_type'],
                    'title' => $template['title'],
                    'content' => $template['content'],
                ]);
            }

            $this->output->progressAdvance();
        }

        $this->output->progressFinish();

        $this->info('Email template has been created successfully');

        return 0;
    }
}
