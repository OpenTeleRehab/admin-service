<?php

namespace App\Jobs;

use App\Enums\ExportStatus;
use App\Exports\PatientRawDataExport;
use App\Exports\QuestionnaireResultExport;
use App\Exports\SurveyExport;
use App\Models\DownloadTracker;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class GenerateExport implements ShouldQueue
{

    const TYPE_QUESTIONNAIRE_RESULT = 'questionnaire_result';
    const TYPE_PATIENT_RAW_DATA = 'patient_raw_data';
    const TYPE_SURVEY_RESULT = 'survey_result';

    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $timeout = 0;

    /** @var $payload */
    protected $payload;

    /**
     * GenerateExport constructor.
     *
     * @param $payload
     */
    public function __construct($payload)
    {
        $this->payload = $payload;
    }

    /**
     * Execute the job.
     *
     * @return void
     */
    public function handle()
    {
        $type = $this->payload['type'];
        if ($type === self::TYPE_QUESTIONNAIRE_RESULT) {
            $filePath = QuestionnaireResultExport::export($this->payload);
        }

        if ($type === self::TYPE_PATIENT_RAW_DATA) {
            $filePath = PatientRawDataExport::export($this->payload);
        }

        if ($type === self::TYPE_SURVEY_RESULT) {
            $filePath = SurveyExport::export($this->payload);
        }

        if ($filePath) {
            DownloadTracker::where('job_id', $this->payload['job_id'])
                ->update([
                    'status' => ExportStatus::SUCCESS,
                    'file_path' => $filePath,
                ]);
        }
    }

    /**
     *  The job failed to process.
     *
     * @param $exception
     *
     * @return void
     */
    public function failed($exception)
    {
        DownloadTracker::where('job_id', $this->payload['job_id'])
            ->update(['status' => ExportStatus::FAILED]);
    }
}
