<?php

namespace App\Jobs;

use App\Events\MfaProgressStatus;
use Throwable;
use App\Models\User;
use App\Models\JobTracker;
use App\Models\MfaSetting;
use App\Services\MfaSettingService;
use Illuminate\Queue\SerializesModels;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Contracts\Queue\ShouldQueue;

class ApplyMfaSettings implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected MfaSetting $mfaSetting;
    protected $authUser;
    protected $jobId;
    protected $isDeleting;

    /**
     * Create a new job instance.
     */
    public function __construct($jobId, MfaSetting $mfaSetting, User $authUser, bool $isDeleting)
    {
        $this->mfaSetting = $mfaSetting;
        $this->authUser = $authUser;
        $this->jobId = $jobId;
        $this->isDeleting = $isDeleting;
    }

    /**
     * Execute the job.
     */
    public function handle(MfaSettingService $mfaSettingService): void
    {
        $this->start();

        $authUserType = $this->authUser->type;

        try {
            $mfaSettingService->recalculate($authUserType, $this->mfaSetting, false);

            if (in_array($this->mfaSetting->role, [User::GROUP_THERAPIST, User::GROUP_PHC_WORKER])) {
                $mfaSettingService->applyToTherapistService(
                    $this->mfaSetting->role,
                    "user.{$this->authUser->id}.mfa",
                    $this->jobId,
                    $this->mfaSetting->id,
                    $this->isDeleting
                );

                return;
            }

            $users = $mfaSettingService->getUsers();

            $mfaSettingService->removeMfaForUsers($users);

            if ($this->isDeleting) {
                $allMfaSettings = $mfaSettingService->getMfaSettings($this->mfaSetting->id);
            } else {
                $allMfaSettings = $mfaSettingService->getMfaSettings();
            }

            foreach ($users as $user) {
                $mfaSettings = $mfaSettingService->getMfaSettingsByUserType($user->type, $allMfaSettings);

                $mfaSetting = $mfaSettingService->resolve($mfaSettings, $user);

                if (!$mfaSetting) {
                    continue;
                }

                if (!$mfaSettingService->apply($user->email, $mfaSetting)) {
                    continue;
                }
            }

            $mfaSetting = MfaSetting::findOrFail($this->mfaSetting->id);

            if ($this->isDeleting) {
                $modelData = $mfaSetting;
                activity()->withoutLogs(function () use ($mfaSetting) {
                    $mfaSetting->delete();
                });

                // Manual activity log
                activity()
                    ->causedBy($this->authUser)
                    ->performedOn($modelData)
                    ->withProperties(['old' => $modelData])
                    ->useLog('admin_service')
                    ->event('deleted')
                    ->log('deleted');
            }

            // end queue
            $this->complete();
        } catch (Throwable $e) {
            throw $e;
        }
    }

    public function failed(Throwable $e)
    {
        JobTracker::where('job_id', $this->jobId)->update([
            'status' => JobTracker::FAILED,
            'message' => $e->getMessage(),
        ]);

        $this->broadcastStatus(JobTracker::FAILED, false, $e->getMessage());
    }

    private function start()
    {
        JobTracker::where('job_id', $this->jobId)->update(['status' => JobTracker::RUNNING]);

        $this->broadcastStatus(JobTracker::RUNNING);
    }

    private function complete()
    {
        JobTracker::where('job_id', $this->jobId)->update(['status' => JobTracker::COMPLETED]);

        $this->broadcastStatus(JobTracker::COMPLETED, $this->isDeleting);
    }

    private function broadcastStatus(string $status, bool $isDeleting = false, ?string $message = null)
    {
        broadcast(new MfaProgressStatus(
            $this->authUser,
            $this->jobId,
            $this->mfaSetting->id,
            $status,
            $isDeleting,
            $message
        ));
    }
}
