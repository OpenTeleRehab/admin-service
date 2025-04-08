<?php

namespace App\Jobs;

use App\Enums\ExportStatus;
use App\Exports\QuestionnaireResultExport;
use App\Models\DownloadTracker;
use App\Models\Forwarder;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Http;

class GenerateExport implements ShouldQueue
{

    const TYPE_QUESTIONNAIRE_RESULT = 'questionnaire_result';

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
