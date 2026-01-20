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
        $authUserType = $this->authUser->type;
        $hiOrganization = Organization::where('sub_domain_name', env('APP_NAME'))->first();

        try {
            $roleHierarchy = [
                User::ADMIN_GROUP_SUPER_ADMIN,
                User::ADMIN_GROUP_ORG_ADMIN,
                User::ADMIN_GROUP_COUNTRY_ADMIN,
                User::ADMIN_GROUP_REGIONAL_ADMIN,
                User::ADMIN_GROUP_CLINIC_ADMIN,
                User::ADMIN_GROUP_PHC_SERVICE_ADMIN,
            ];
            $orderByRoles = "'" . implode("','", $roleHierarchy) . "'";

            $excludeRoles = [];

            foreach ($roleHierarchy as $role) {
                if ($role === $authUserType) break;

                $excludeRoles[] = $role;
            }

            $excludeRoles[] = $authUserType;

            $query = MfaSetting::where('role', $this->mfaSetting->role);

            if (!empty($excludeRoles)) {
                $query->whereNotIn('created_by_role', $excludeRoles);
            }

            if ($authUserType === User::ADMIN_GROUP_ORG_ADMIN) {
                $query->whereJsonContains('organizations', $hiOrganization->id);
            }

            $mfaSettings = $query->orderByRaw('FIELD(created_by_role, ' . $orderByRoles . ')')->get();

            foreach ($mfaSettings as $mfaSetting) {
                if (!$this->isScopeMatched($this->mfaSetting, $mfaSetting)) {
                    continue;
                }

                if (MfaSettingHelper::validateMfaEnforcement($mfaSetting, $this->mfaSetting->mfa_enforcement) && in_array($hiOrganization?->id, $mfaSetting->organizations)) {
                    $mfaSetting->update(['mfa_enforcement' => $this->mfaSetting->mfa_enforcement]);
                }
            }

            if ($authUserType === User::ADMIN_GROUP_ORG_ADMIN) {
                foreach ($mfaSettings as $mfaSetting) {
                    $mfaSetting->update([
                        'mfa_expiration_duration' => $this->mfaSetting?->mfa_expiration_duration,
                        'skip_mfa_setup_duration' => $this->mfaSetting?->skip_mfa_setup_duration,
                    ]);
                }
            }

            $federatedDomains = array_map(fn($d) => strtolower(trim($d)), explode(',', env('FEDERATED_DOMAINS', '')));

            $freshMfaSettings = MfaSetting::where('role', $this->mfaSetting->role)
                ->orderByRaw('FIELD(created_by_role, ' . $orderByRoles . ')')
                ->get();

            foreach ($freshMfaSettings as $mfaSetting) {
                if (in_array($mfaSetting->role, [User::GROUP_THERAPIST, User::GROUP_PHC_WORKER])) {
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

                $internalUsersQuery = User::query()
                    ->where(function ($query) use ($federatedDomains) {
                        foreach ($federatedDomains as $domain) {
                            $query->whereRaw('LOWER(email) NOT LIKE ?', ['%' . $domain]);
                        }
                    })
                    ->where('type', $mfaSetting->role);

                switch ($mfaSetting->role) {
                    case User::ADMIN_GROUP_COUNTRY_ADMIN:
                        if (!empty($mfaSetting->country_ids)) {
                            $internalUsersQuery->whereIn('country_id', $mfaSetting->country_ids);
                        }
                        break;

                    case User::ADMIN_GROUP_REGIONAL_ADMIN:
                        if (!empty($mfaSetting->region_ids)) {
                            $internalUsersQuery->whereIn('region_id', $mfaSetting->region_ids);
                        }
                        break;

                    case User::ADMIN_GROUP_CLINIC_ADMIN:
                        if (!empty($mfaSetting->clinic_ids)) {
                            $internalUsersQuery->whereIn('clinic_id', $mfaSetting->clinic_ids);
                        }
                        break;

                    case User::ADMIN_GROUP_PHC_SERVICE_ADMIN:
                        if (!empty($mfaSetting->phc_service_ids)) {
                            $internalUsersQuery->whereIn('phc_service_id', $mfaSetting->phc_service_ids);
                        }
                        break;
                }

                $users = $internalUsersQuery->get();

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

    private function isScopeMatched(MfaSetting $source, MfaSetting $target): bool
    {
        $has = fn ($a, $b) => !empty(array_intersect($a ?? [], $b ?? []));

        return match ($source->created_by_role) {
            User::ADMIN_GROUP_ORG_ADMIN => $has($source->country_ids, $target->country_ids),

            User::ADMIN_GROUP_COUNTRY_ADMIN => $has($source->region_ids, $target->region_ids),

            User::ADMIN_GROUP_REGIONAL_ADMIN =>
                match ($source->role) {
                    User::ADMIN_GROUP_PHC_SERVICE_ADMIN,
                    User::GROUP_PHC_WORKER =>
                        $has($source->phc_service_ids, $target->phc_service_ids),

                    User::ADMIN_GROUP_CLINIC_ADMIN,
                    User::GROUP_THERAPIST =>
                        $has($source->clinic_ids, $target->clinic_ids),

                    default => true,
                },

            User::ADMIN_GROUP_PHC_SERVICE_ADMIN => $has($source->phc_service_ids, $target->phc_service_ids),

            default => true,
        };
    }

    public function failed(\Throwable $exception): void
    {
        JobTracker::where('job_id', $this->jobId)->update([
            'status' => JobTracker::FAILED,
            'message' => $exception->getMessage(),
        ]);
    }
}
