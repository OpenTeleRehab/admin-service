<?php

namespace App\Jobs;

use Throwable;
use Carbon\Carbon;
use App\Models\User;
use App\Models\Forwarder;
use App\Models\JobTracker;
use App\Models\MfaSetting;
use Illuminate\Support\Str;
use App\Models\Organization;
use App\Helpers\KeycloakHelper;
use App\Helpers\MfaSettingHelper;
use Illuminate\Support\Facades\Http;
use Illuminate\Queue\SerializesModels;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Support\Facades\Log;

class UpdateFederatedUsersMfaJob implements ShouldQueue
{
    use InteractsWithQueue, Queueable, SerializesModels;

    protected $mfaSetting;
    protected $authUser;
    protected $jobId;

    /**
     * Create a new job instance.
     */
    public function __construct(MfaSetting $mfaSetting, User $authUser)
    {
        $this->mfaSetting = $mfaSetting;
        $this->authUser = $authUser;
        $this->jobId = Str::uuid()->toString();

        JobTracker::create([
            'job_id' => $this->jobId,
            'status' => JobTracker::RUNNING,
            'trackable_type' => MfaSetting::class,
            'trackable_id' => $mfaSetting->id,
        ]);
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        $roleAtOrBelowCurrentRole = $this->getRolesAtOrBelow($this->authUser->type);
        $mfaSettings = MfaSetting::where('role', $this->mfaSetting->role)->whereIn('created_by_role', $roleAtOrBelowCurrentRole);
        $hiOrganization = Organization::where('sub_domain_name', env('APP_NAME'))->first();

        try {
            $baseQuery = MfaSetting::where('role', $this->mfaSetting->role);

            $orderByRoles = "'"
                . User::ADMIN_GROUP_SUPER_ADMIN . "', '"
                . User::ADMIN_GROUP_ORG_ADMIN . "', '"
                . User::ADMIN_GROUP_COUNTRY_ADMIN . "', '"
                . User::ADMIN_GROUP_REGIONAL_ADMIN . "', '"
                . User::ADMIN_GROUP_CLINIC_ADMIN . "', '"
                . User::ADMIN_GROUP_PHC_SERVICE_ADMIN . "'";

            if ($this->authUser->type === User::ADMIN_GROUP_SUPER_ADMIN) {
                $mfaSettings = $baseQuery
                    ->orderByRaw('FIELD(created_by_role, ' . $orderByRoles . ')')
                    ->get();
            } else if ($this->authUser->type === User::ADMIN_GROUP_ORG_ADMIN) {
                $mfaSettings = $baseQuery
                    ->whereNotIn('created_by_role', [User::ADMIN_GROUP_SUPER_ADMIN])
                    ->whereJsonContains('organizations', $hiOrganization->id)
                    ->orderByRaw('FIELD(created_by_role, ' . $orderByRoles . ')')
                    ->get();
            } else if ($this->authUser->type === User::ADMIN_GROUP_COUNTRY_ADMIN) {
                $mfaSettings = $baseQuery
                    ->whereNotIn('created_by_role', [User::ADMIN_GROUP_SUPER_ADMIN, User::ADMIN_GROUP_ORG_ADMIN])
                    ->orderByRaw('FIELD(created_by_role, ' . $orderByRoles . ')')
                    ->get();
            } else if ($this->authUser->type === User::ADMIN_GROUP_REGIONAL_ADMIN) {
                $mfaSettings = $baseQuery
                    ->whereNotIn('created_by_role', [User::ADMIN_GROUP_SUPER_ADMIN, User::ADMIN_GROUP_ORG_ADMIN, User::ADMIN_GROUP_COUNTRY_ADMIN])
                    ->orderByRaw('FIELD(created_by_role, ' . $orderByRoles . ')')
                    ->get();
            } else if ($this->authUser->type === User::ADMIN_GROUP_CLINIC_ADMIN) {
                $mfaSettings = $baseQuery
                    ->whereNotIn('created_by_role', [User::ADMIN_GROUP_SUPER_ADMIN, User::ADMIN_GROUP_ORG_ADMIN, User::ADMIN_GROUP_COUNTRY_ADMIN, User::ADMIN_GROUP_REGIONAL_ADMIN])
                    ->orderByRaw('FIELD(created_by_role, ' . $orderByRoles . ')')
                    ->get();
            } else if ($this->authUser->type === User::ADMIN_GROUP_PHC_SERVICE_ADMIN) {
                $mfaSettings = $baseQuery
                    ->whereNotIn('created_by_role', [User::ADMIN_GROUP_SUPER_ADMIN, User::ADMIN_GROUP_ORG_ADMIN, User::ADMIN_GROUP_COUNTRY_ADMIN, User::ADMIN_GROUP_REGIONAL_ADMIN])
                    ->orderByRaw('FIELD(created_by_role, ' . $orderByRoles . ')')
                    ->get();
            } else {
                $mfaSettings = $baseQuery
                    ->orderByRaw('FIELD(created_by_role, ' . $orderByRoles . ')')
                    ->get();
            }

            foreach ($mfaSettings as $mfaSetting) {
                if ($this->checkRoleLevel($mfaSetting->created_by_role) >= $this->checkRoleLevel($this->authUser->type)) {
                    continue;
                }

                if (MfaSettingHelper::validateMfaEnforcement($mfaSetting, $this->mfaSetting->mfa_enforcement) && in_array($hiOrganization?->id, $mfaSetting->organizations)) {
                    $mfaSetting->update(['mfa_enforcement' => $mfaSetting->mfa_enforcement]);
                }
            }

            if ($this->authUser->type === User::ADMIN_GROUP_ORG_ADMIN) {
                foreach ($mfaSettings as $mfaSetting) {
                    $mfaSetting->update([
                        'mfa_expiration_duration' => $this->mfaSetting?->mfa_expiration_duration,
                        'skip_mfa_setup_duration' => $this->mfaSetting?->skip_mfa_setup_duration,
                    ]);
                }
            }

            $federatedDomains = array_map(fn($d) => strtolower(trim($d)), explode(',', env('FEDERATED_DOMAINS', '')));

            $internalUsers = User::query()
                ->where(function ($query) use ($federatedDomains) {
                    foreach ($federatedDomains as $domain) {
                        $query->whereRaw('LOWER(email) NOT LIKE ?', ['%' . strtolower($domain)]);
                    }
                });

            $freshMfaSettings = MfaSetting::where('role', $this->mfaSetting->role)
                ->orderByRaw('FIELD(created_by_role, ' . $orderByRoles . ')')
                ->get();

            foreach ($freshMfaSettings as $mfaSetting) {
                $internalUsers->where('type', $mfaSetting->role);

                if ($mfaSetting->role === User::GROUP_THERAPIST || $mfaSetting->role === User::GROUP_PHC_WORKER) {
                    $accessToken = Forwarder::getAccessToken(Forwarder::THERAPIST_SERVICE);
                    $response = Http::withToken($accessToken)
                        ->post(env('THERAPIST_SERVICE_URL') . '/mfa-settings', [
                            'country_ids' => $mfaSetting->country_ids,
                            'region_ids' => $mfaSetting->region_ids,
                            'clinic_ids' => $mfaSetting->clinic_ids,
                            'phc_service_ids' => $mfaSetting->phc_service_ids,
                            'role' => $mfaSetting->role,
                            'mfa_enforcement' => $mfaSetting->mfa_enforcement,
                            'mfa_expiration_duration' => $mfaSetting->mfa_expiration_duration,
                            'skip_mfa_setup_duration' => $mfaSetting->skip_mfa_setup_duration,
                        ]);

                    if (!$response->successful()) {
                        JobTracker::where('job_id', $this->jobId)->update([
                            'status' => JobTracker::FAILED,
                            'message' => $response->json('message'),
                        ]);
                        throw new \Exception('Therapist request failed: ' . $response->body());
                    }

                    continue;
                }

                if (!empty($mfaSetting->country_ids) && empty($mfaSetting->clinic_ids)) {
                    $internalUsers->whereIn('country_id', $mfaSetting->country_ids);
                } else if ($mfaSetting->clinic_ids) {
                    $internalUsers->whereIn('clinic_id', $mfaSetting->clinic_ids);
                }

                $users = $internalUsers->get();

                foreach ($users as $user) {
                    if ($mfaSetting->mfa_enforcement === MfaSetting::MFA_DISABLE) {
                        KeycloakHelper::deleteUserCredentialByType($user->email, 'otp');
                    }

                    $keycloakUser = KeycloakHelper::getKeycloakUserByUsername($user->email);

                    $existingAttributes = $keycloakUser['attributes'] ?? [];

                    $payload = [
                        'mfaEnforcement' => $mfaSetting->mfa_enforcement ?? null,
                        'trustedDeviceMaxAge' => $mfaSetting->mfa_expiration_duration ?? null,
                        'skipMfaMaxAge' => $mfaSetting->skip_mfa_setup_duration ?? null,
                    ];

                    if (isset($existingAttributes['skipMfaUntil'])) {
                        $date = Carbon::parse($existingAttributes['skipMfaUntil'][0]);

                        $now = Carbon::now();

                        $futureDate = $now->copy()->addSeconds($mfaSetting->skip_mfa_setup_duration);

                        $isoString = $futureDate->format('Y-m-d\TH:i:s.u\Z');

                        if (!$date->isPast()) {
                            $payload['skipMfaUntil'] = $isoString;
                        }
                    }

                    KeycloakHelper::setUserAttributes(
                        $user->email,
                        $payload,
                    );
                }
            }

            // end queue
            JobTracker::where('job_id', $this->jobId)->update(['status' => JobTracker::COMPLETED]);
        } catch (Throwable $e) {
            JobTracker::where('job_id', $this->jobId)->update([
                'status' => JobTracker::FAILED,
                'message' => $e->getMessage(),
            ]);

            throw $e;
        }
    }

    private function checkMfaEnforcementLevel($mfaEnforcement)
    {
        $definedLevel = [
            MfaSetting::MFA_DISABLE => 1,
            MfaSetting::MFA_RECOMMEND => 2,
            MfaSetting::MFA_ENFORCE => 3,
        ];

        return $definedLevel[$mfaEnforcement];
    }

    private function checkRoleLevel($role)
    {
        $definedLevel = [
            User::GROUP_THERAPIST => 1,
            User::GROUP_PHC_WORKER => 1,
            User::ADMIN_GROUP_PHC_SERVICE_ADMIN => 2,
            User::ADMIN_GROUP_CLINIC_ADMIN => 2,
            User::ADMIN_GROUP_REGIONAL_ADMIN => 3,
            User::ADMIN_GROUP_COUNTRY_ADMIN => 4,
            User::ADMIN_GROUP_ORG_ADMIN => 5,
            User::ADMIN_GROUP_SUPER_ADMIN => 6
        ];

        return $definedLevel[$role];
    }

    private function getRolesAtOrBelow($role)
    {
        $definedLevel = [
            User::GROUP_THERAPIST => 1,
            User::GROUP_PHC_WORKER => 1,
            User::ADMIN_GROUP_PHC_SERVICE_ADMIN => 2,
            User::ADMIN_GROUP_CLINIC_ADMIN => 2,
            User::ADMIN_GROUP_REGIONAL_ADMIN => 3,
            User::ADMIN_GROUP_COUNTRY_ADMIN => 4,
            User::ADMIN_GROUP_ORG_ADMIN => 5,
            User::ADMIN_GROUP_SUPER_ADMIN => 6
        ];

        $currentLevel = $this->checkRoleLevel($role);

        $underRoles = array_filter($definedLevel, function ($level) use ($currentLevel) {
            return $level <= $currentLevel;
        });

        return array_keys($underRoles);
    }

    public function failed(\Throwable $exception): void
    {
        JobTracker::where('job_id', $this->jobId)->update([
            'status' => JobTracker::FAILED,
            'message' => $exception->getMessage(),
        ]);
    }
}
