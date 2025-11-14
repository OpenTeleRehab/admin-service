<?php

namespace App\Jobs;

use App\Helpers\KeycloakHelper;
use App\Models\Forwarder;
use App\Models\JobTracker;
use App\Models\MfaSetting;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Http;

class UpdateKeycloakUserAttributes implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public array $data;
    public string $jobId;
    public int $authId;

    /**
     * Max attempts and timeout
     */
    public $tries = 3;
    public $timeout = 600;

    public function __construct(array $data, string $jobId, int $authId)
    {
        $this->data = $data;
        $this->jobId = $jobId;
        $this->authId = $authId;
    }

    public function handle(): void
    {
        $role = $this->data['role'] ?? null;
        $countryIds = $this->data['country_ids'] ?? [];
        $clinicIds = $this->data['clinic_ids'] ?? [];
        $attributes = $this->data['attributes'] ?? [];
        $newEnforcement = $attributes['mfa_enforcement'] ?? null;

        try {
            if (
                !$role ||
                empty($attributes) ||
                empty($attributes['mfa_enforcement'] ?? null)
            ) {
                JobTracker::where('job_id', $this->jobId)->update([
                    'status' => JobTracker::FAILED,
                    'message' => 'Missing required attributes',
                ]);
                return;
            }

            $rolesToUpdate = [];

            switch($attributes['mfa_enforcement']) {
                case MfaSetting::MFA_DISABLE:
                case MfaSetting::MFA_RECOMMEND:
                case MfaSetting::MFA_ENFORCE:
                    foreach(MfaSetting::ROLE_LEVEL as $roleLevel => $level) {
                        if ($level >= MfaSetting::ROLE_LEVEL[$role]) {
                            $rolesToUpdate[] = $roleLevel;
                        }
                    }
            }

            $authUser = User::find($this->authId);
            $authUserData = KeycloakHelper::getKeycloakUserByUsername($authUser->email);
            $authUserAttributes = $authUserData['attributes'] ?? [];
            $authUserEnforcement = isset($authUserAttributes[MfaSetting::MFA_KEY_ENFORCEMENT])
                ? (is_array($authUserAttributes[MfaSetting::MFA_KEY_ENFORCEMENT])
                    ? $authUserAttributes[MfaSetting::MFA_KEY_ENFORCEMENT][0]
                    : $authUserAttributes[MfaSetting::MFA_KEY_ENFORCEMENT])
                : null;

            if (!empty($rolesToUpdate)) {
                if (($key = array_search('therapist', $rolesToUpdate, true)) !== false) {
                    unset($rolesToUpdate[$key]);

                    $access_token = Forwarder::getAccessToken(Forwarder::THERAPIST_SERVICE);
                    $response = Http::withToken($access_token)
                        ->post(env('THERAPIST_SERVICE_URL') . '/mfa-settings', [
                            'country_ids' => $countryIds,
                            'clinic_ids' => $clinicIds,
                            'attributes' => $attributes,
                            'admin_enforcement' => $authUserEnforcement,
                        ]);

                    if (!$response->successful()) {
                        throw new \Exception('Therapist request failed: ' . $response->body());
                    }

                    $jobId = $response->json('job_id');

                    $maxWait = 600;
                    $interval = 3;
                    $elapsed = 0;

                    while ($elapsed < $maxWait) {
                        $statusResponse = Http::withToken($access_token)
                            ->get(env('THERAPIST_SERVICE_URL') . "/mfa-settings/{$jobId}");

                        $statusData = $statusResponse->json();
                        if ($statusData['status'] === 'COMPLETED') break;
                        if ($statusData['status'] === 'FAILED') {
                            throw new \Exception('Therapist job failed: ' . $statusData['message']);
                        }

                        sleep($interval);
                        $elapsed += $interval;
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
                                    $query->orWhere('email', 'NOT LIKE', "%$domain");
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

                        $existingAttributes['available_enforcement'] = $newEnforcement;

                        if (isset($attributes['mfa_expiration_duration'])) {
                            $existingAttributes[MfaSetting::MFA_MAX_AGE] = $attributes['mfa_expiration_duration'];
                        }

                        if (isset($attributes['skip_mfa_setup_duration'])) {
                            $existingAttributes[MfaSetting::MFA_SKIP_MAX_AGE] = $attributes['skip_mfa_setup_duration'];
                        }

                        $oldEnforcement = isset($existingAttributes[MfaSetting::MFA_KEY_ENFORCEMENT])
                            ? (is_array($existingAttributes[MfaSetting::MFA_KEY_ENFORCEMENT])
                                ? $existingAttributes[MfaSetting::MFA_KEY_ENFORCEMENT][0]
                                : $existingAttributes[MfaSetting::MFA_KEY_ENFORCEMENT])
                            : null;

                        if (
                            $oldEnforcement == null ||
                            (MfaSetting::ENFORCEMENT_LEVEL[$newEnforcement] ?? 0) <=
                            (MfaSetting::ENFORCEMENT_LEVEL[$oldEnforcement] ?? 4) &&
                            (
                                $authUserEnforcement == null ||
                                (MfaSetting::ENFORCEMENT_LEVEL[$newEnforcement] ?? 0) <=
                                (MfaSetting::ENFORCEMENT_LEVEL[$authUserEnforcement] ?? 0)
                            )
                        ) {
                            if ($newEnforcement === MfaSetting::MFA_DISABLE) {
                                KeycloakHelper::deleteUserCredentialByType($user->email, 'otp');
                            }
                            $existingAttributes[MfaSetting::MFA_KEY_ENFORCEMENT] = $newEnforcement;
                        }

                        KeycloakHelper::updateUserAttributesById($userData['id'], $existingAttributes);
                    }
                }
            }

            $roleMfaSettings = [];

            foreach(MfaSetting::ROLE_LEVEL as $roleLevel => $level) {
                if ($level >= MfaSetting::ROLE_LEVEL[$role]) {
                    $roleMfaSettings[] = $roleLevel;
                }
            }

            $mfaSettingQuery = MfaSetting::query()
                ->whereHas('user', fn($query) => $query->whereIn('type', $roleMfaSettings))
                ->whereIn('role', $roleMfaSettings)
                ->where(function ($query) use ($countryIds, $clinicIds) {
                    if (!empty($countryIds)) {
                        foreach ($countryIds as $id) {
                            $query->orWhereRaw('JSON_CONTAINS(country_ids, ?)', [json_encode($id)])
                                ->orWhereRaw('JSON_CONTAINS(country_ids, ?)', [json_encode((string) $id)]);
                        }
                    }

                    if (!empty($clinicIds)) {
                        foreach ($clinicIds as $id) {
                            $query->orWhereRaw('JSON_CONTAINS(clinic_ids, ?)', [json_encode($id)])
                                ->orWhereRaw('JSON_CONTAINS(clinic_ids, ?)', [json_encode((string) $id)]);
                        }
                    }
                });

            $mfaSettings = $mfaSettingQuery->get();
            
            foreach($mfaSettings as $mfaSetting) {
                $currentEnforcement = $mfaSetting->attributes[MfaSetting::MFA_KEY_ENFORCEMENT] ?? null;
                if (
                    $currentEnforcement && 
                    MfaSetting::ENFORCEMENT_LEVEL[$currentEnforcement] >
                    MfaSetting::ENFORCEMENT_LEVEL[$newEnforcement]
                ) {
                    MfaSetting::where('id', $mfaSetting->id)
                        ->update(['attributes->mfa_enforcement' => $newEnforcement]);
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
