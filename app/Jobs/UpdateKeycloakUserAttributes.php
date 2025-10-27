<?php

namespace App\Jobs;

use App\Helpers\KeycloakHelper;
use App\Models\JobTracker;
use App\Models\MfaSetting;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class UpdateKeycloakUserAttributes implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public array $data;
    public string $jobId;

    /**
     * Max attempts and timeout
     */
    public $tries = 3;
    public $timeout = 300;

    public function __construct(array $data, string $jobId)
    {
        $this->data = $data;
        $this->jobId = $jobId;
    }

    public function handle(): void
    {
        $role = $this->data['role'] ?? null;
        $countryIds = $this->data['country_ids'] ?? [];
        $clinicIds = $this->data['clinic_ids'] ?? [];
        $attributes = $this->data['attributes'] ?? [];

        try {
            if (
                !$role ||
                empty($attributes) ||
                empty($attributes[MfaSetting::MFA_KEY_ENFORCEMENT] ?? null)
            ) {
                JobTracker::where('job_id', $this->jobId)->update([
                    'status' => JobTracker::FAILED,
                    'message' => 'Missing required attributes',
                ]);
                return;
            }

            $rolesToUpdate = [];

            switch($attributes[MfaSetting::MFA_KEY_ENFORCEMENT]) {
                case MfaSetting::MFA_DISABLE:
                case MfaSetting::MFA_RECOMMEND:
                case MfaSetting::MFA_ENFORCE:
                    foreach(MfaSetting::ROLE_LEVEL as $roleLevel => $level) {
                        if ($level > MfaSetting::ROLE_LEVEL[$role]) {
                            $rolesToUpdate[] = $roleLevel;
                        }
                    }
            }

            if (!empty($rolesToUpdate)) {
                $usersQuery = User::whereIn('type', $rolesToUpdate);

                if (!empty($countryIds)) {
                    $usersQuery->whereIn('country_id', $countryIds);
                }

                if (!empty($clinicIds)) {
                    $usersQuery->whereIn('clinic_id', $clinicIds);
                }

                $externalDomains = explode(',', env('FEDERATED_DOMAINS', ''));

                if (!empty($externalDomains)) {
                    $usersQuery->where(function($query) use ($externalDomains) {
                        foreach ($externalDomains as $domain) {
                            $domain = trim($domain);
                            if ($domain) {
                                $query->orWhere('email', 'LIKE', "%$domain");
                            }
                        }
                    });
                }

                $users = $usersQuery->get();

                foreach ($users as $user) {
                    $userData = KeycloakHelper::getKeycloakUserByUsername($user->email);
                    if (!$userData) {
                        continue;
                    }

                    $existingAttributes = $userData['attributes'] ?? [];

                    $newEnforcement = $attributes[MfaSetting::MFA_KEY_ENFORCEMENT] ?? null;
                    $oldEnforcement = is_array($existingAttributes[MfaSetting::MFA_KEY_ENFORCEMENT]) 
                        ? $existingAttributes[MfaSetting::MFA_KEY_ENFORCEMENT][0]
                        : $existingAttributes[MfaSetting::MFA_KEY_ENFORCEMENT] ?? null;

                    if (
                        in_array($newEnforcement, [
                            MfaSetting::MFA_DISABLE,
                            MfaSetting::MFA_RECOMMEND,
                            MfaSetting::MFA_ENFORCE
                        ], true) &&
                        (MfaSetting::ENFORCEMENT_LEVEL[$newEnforcement] ?? 0) >
                        (MfaSetting::ENFORCEMENT_LEVEL[$oldEnforcement] ?? 0)
                    ) {
                        continue;
                    }

                    foreach ($attributes as $key => $value) {
                        if (
                            $key === MfaSetting::MFA_KEY_ENFORCEMENT &&
                            $attributes[$key] === MfaSetting::MFA_DISABLE
                        ) {
                            KeycloakHelper::deleteUserCredentialByType($user->email, 'otp');
                        }

                        $existingAttributes[$key] = is_array($value) ? $value : [$value];
                    }

                    KeycloakHelper::updateUserAttributesById($userData['id'], $existingAttributes);
                }
            }

            JobTracker::where('job_id', $this->jobId)->update(['status' => JobTracker::COMPLETED]);
        } catch (\Throwable $e) {
            JobTracker::where('job_id', $this->jobId)->update([
                'status' => JobTracker::FAILED,
                'message' => $e->getMessage(),
            ]);

            throw $e;
        }
    }

    public function failed(\Throwable $exception): void
    {
        JobTracker::where('job_id', $this->jobId)->update([
            'status' => JobTracker::FAILED,
            'message' => $exception->getMessage(),
        ]);
    }
}
