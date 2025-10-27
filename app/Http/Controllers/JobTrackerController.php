<?php

namespace App\Http\Controllers;

use App\Models\JobTracker;
use Symfony\Component\HttpFoundation\StreamedResponse;

class JobTrackerController extends Controller
{
    public function stream(string $jobId): StreamedResponse
    {
        set_time_limit(0);

        return new StreamedResponse(function () use ($jobId) {
            @ob_end_flush();
            @ob_implicit_flush(true);

            $startTime = time();
            $timeout = 300;

            while (true) {
                try {
                    $job = JobTracker::where('job_id', $jobId)->first();

                    if ($job) {
                        echo "data: " . json_encode([
                            'status' => $job->status,
                            'message' => $job->message,
                        ]) . "\n\n";

                        @ob_flush();
                        flush();

                        if (in_array($job->status, [JobTracker::COMPLETED, JobTracker::FAILED])) {
                            break;
                        }
                    }

                    if ((time() - $startTime) > $timeout) {
                        echo "data: " . json_encode([
                            'status' => JobTracker::FAILED,
                            'message' => 'Timeout exceeded',
                        ]) . "\n\n";
                        @ob_flush();
                        flush();
                        break;
                    }

                    sleep(3);
                } catch (\Throwable $e) {
                    echo "data: " . json_encode([
                        'status' => JobTracker::FAILED,
                        'message' => $e->getMessage(),
                    ]) . "\n\n";
                    @ob_flush();
                    flush();
                    break;
                }
            }
        }, 200, [
            'Content-Type' => 'text/event-stream',
            'Cache-Control' => 'no-cache',
            'Connection' => 'keep-alive',
            'X-Accel-Buffering' => 'no',
        ]);
    }
}
