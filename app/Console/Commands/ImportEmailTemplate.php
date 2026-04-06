<?php

namespace App\Console\Commands;

use App\Events\ApplyEmailTemplateAutoTranslationEvent;
use App\Models\EmailTemplate;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Spatie\Activitylog\Facades\Activity;

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
        // Disable activity logging for email template import
        Activity::disableLogging();

        $templates = Storage::get('email_template/content.json');
        $templates = str_replace(
            ['${ADMIN_APP_URL}', '${THERAPIST_APP_URL}'],
            [env('APP_URL'), env('THERAPIST_APP_URL')],
            $templates
        );
        $templates = json_decode($templates, true);

        $this->output->progressStart(count($templates));

        foreach ($templates as $template) {
            $prefix = Str::slug($template['content_type']);

            $exist = EmailTemplate::where('prefix', $prefix)->exists();

            if (!$exist) {
                $emailTemplate = EmailTemplate::create([
                    'prefix' => $prefix,
                    'content_type' => $template['content_type'],
                    'title' => $template['title'],
                    'content' => $template['content'],
                ]);

                // Add automatic translation for email template.
                event(new ApplyEmailTemplateAutoTranslationEvent($emailTemplate));
            }

            $this->output->progressAdvance();
        }

        $this->output->progressFinish();

        // Re-enable activity logging after email template import
        Activity::enableLogging();

        $this->info('Email template has been created successfully');

        return 0;
    }
}
