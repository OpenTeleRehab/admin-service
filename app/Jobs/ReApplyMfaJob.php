<?php

namespace App\Jobs;

use App\Events\MfaProgressStatus;
use App\Helpers\KeycloakHelper;
use App\Models\Forwarder;
use App\Models\JobTracker;
use App\Models\MfaSetting;
use App\Models\Organization;
use App\Models\User;
use Carbon\Carbon;
use Throwable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class ReApplyMfaJob implements ShouldQueue
{
    use Dispatchable, Queueable;

    protected $mfaSetting;
    protected $authUser;
    protected $jobId;

    /**
     * Create a new job instance.
     */
    public function __construct($jobId, MfaSetting $mfaSetting, User $authUser)
    {
        $this->mfaSetting = $mfaSetting;
        $this->authUser = $authUser;
        $this->jobId = $jobId;
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        JobTracker::where('job_id', $this->jobId)->update(['status' => JobTracker::RUNNING]);

        broadcast(new MfaProgressStatus(
            $this->authUser,
            $this->jobId,
            $this->mfaSetting->id,
            JobTracker::RUNNING,
        ));

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

            $query = MfaSetting::query();

            if ($authUserType === User::ADMIN_GROUP_ORG_ADMIN) {
                $query->whereJsonContains('organizations', $hiOrganization->id);
            }

            $freshMfaSettings = $query->where('id', '!=', $this->mfaSetting->id)->orderByRaw('FIELD(created_by_role, ' . $orderByRoles . ')')->get();

            $federatedDomains = array_map(fn($d) => strtolower(trim($d)), explode(',', env('FEDERATED_DOMAINS', '')));

            $internalUsersQuery = User::query()
                ->where(function ($query) use ($federatedDomains) {
                    foreach ($federatedDomains as $domain) {
                        $query->whereRaw('LOWER(email) NOT LIKE ?', ['%' . $domain]);
                    }
                });

            $countryIdsFromMfaSettings = $freshMfaSettings->whereIn('created_by_role', [User::ADMIN_GROUP_SUPER_ADMIN, User::ADMIN_GROUP_ORG_ADMIN])->pluck('country_ids')->flatten()->filter()->unique()->values()->all();
            $regionIdsFromMfaSettings = $freshMfaSettings->where('created_by_role', User::ADMIN_GROUP_COUNTRY_ADMIN)->pluck('region_ids')->flatten()->filter()->unique()->values()->all();
            $clinicIdsFromMfaSettings = $freshMfaSettings->where('created_by_role', User::ADMIN_GROUP_REGIONAL_ADMIN)->pluck('clinic_ids')->flatten()->filter()->unique()->values()->all();
            $phcServiceIdsFromMfaSettings = $freshMfaSettings->where('created_by_role', User::ADMIN_GROUP_REGIONAL_ADMIN)->pluck('phc_service_ids')->flatten()->filter()->unique()->values()->all();

            $countryAdminsToRemoveMfa = (clone $internalUsersQuery)->where('type', User::ADMIN_GROUP_COUNTRY_ADMIN)
                ->whereNotIn('country_id', $countryIdsFromMfaSettings)
                ->get();
            $regionalAdminsToRemoveMfa = (clone $internalUsersQuery)->where('type', User::ADMIN_GROUP_REGIONAL_ADMIN)
                ->whereHas('regions', function ($query) use ($regionIdsFromMfaSettings) {
                    $query->whereNotIn('id', $regionIdsFromMfaSettings);
                })
                ->get();
            $clinicAdminsToRemoveMfa = (clone $internalUsersQuery)->where('type', User::ADMIN_GROUP_CLINIC_ADMIN)
                ->whereNotIn('clinic_id', $clinicIdsFromMfaSettings)
                ->get();
            $phcServiceAdminsToRemoveMfa = (clone $internalUsersQuery)->where('type', User::ADMIN_GROUP_PHC_SERVICE_ADMIN)
                ->whereNotIn('phc_service_id', $phcServiceIdsFromMfaSettings)
                ->get();

            $usersToRemoveMfa = array_merge($countryAdminsToRemoveMfa->toArray(), $regionalAdminsToRemoveMfa->toArray(), $clinicAdminsToRemoveMfa->toArray(), $phcServiceAdminsToRemoveMfa->toArray());

            foreach ($usersToRemoveMfa as $user) {
                $keycloakUser = KeycloakHelper::getKeycloakUserByUsername($user['email']);

                if (!$keycloakUser) {
                    Log::warning("Keycloak user not found for email: {$user['email']}");
                    continue;
                }

                KeycloakHelper::deleteUserCredentialByType($user['email'], 'otp');

                $existingAttributes = $keycloakUser['attributes'] ?? [];

                $payload = [
                    'mfaEnforcement' => '',
                    'trustedDeviceMaxAge' => '',
                    'skipMfaMaxAge' => '',
                    'skipMfaUntil' => '',
                ];

                $payload = array_merge($existingAttributes, $payload);

                KeycloakHelper::setUserAttributes(
                    $user['email'],
                    $payload,
                );
            }

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
                            'mfa_expiration_unit' => $mfaSetting->mfa_expiration_unit,
                            'skip_mfa_setup_unit' => $mfaSetting->skip_mfa_setup_unit,
                            'mfa_expiration_duration_in_seconds' => $mfaSetting->mfa_expiration_duration_in_seconds,
                            'skip_mfa_setup_duration_in_seconds' => $mfaSetting->skip_mfa_setup_duration_in_seconds,
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
                            $internalUsersQuery->whereHas('regions', function ($query) use ($mfaSetting) {
                                $query->whereIn('id', $mfaSetting->region_ids);
                            });
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
                    $keycloakUser = KeycloakHelper::getKeycloakUserByUsername($user->email);

                    if (!$keycloakUser) {
                        Log::warning("Keycloak user not found for email: {$user->email}");
                        continue;
                    }

                    if ($mfaSetting->mfa_enforcement === MfaSetting::MFA_DISABLE) {
                        KeycloakHelper::deleteUserCredentialByType($user->email, 'otp');
                    }

                    $existingAttributes = $keycloakUser['attributes'] ?? [];

                    $payload = [
                        'mfaEnforcement' => $mfaSetting->mfa_enforcement ?? null,
                        'trustedDeviceMaxAge' => $mfaSetting->mfa_expiration_duration_in_seconds ?? null,
                        'skipMfaMaxAge' => $mfaSetting->skip_mfa_setup_duration_in_seconds ?? null,
                    ];

                    if (isset($existingAttributes['skipMfaUntil'])) {
                        $date = Carbon::parse($existingAttributes['skipMfaUntil'][0]);

                        $now = Carbon::now();

                        $futureDate = $now->copy()->addSeconds($mfaSetting->skip_mfa_setup_duration_in_seconds);

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
            $mfaSetting = MfaSetting::findOrFail($this->mfaSetting->id);
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

            broadcast(new MfaProgressStatus(
                $this->authUser,
                $this->jobId,
                $this->mfaSetting->id,
                JobTracker::COMPLETED,
                true,
            ));
        } catch (Throwable $e) {
            JobTracker::where('job_id', $this->jobId)->update([
                'status' => JobTracker::FAILED,
                'message' => $e->getMessage(),
            ]);

            broadcast(new MfaProgressStatus(
                $this->authUser,
                $this->jobId,
                $this->mfaSetting->id,
                JobTracker::FAILED,
                false,
                $e->getMessage(),
            ));

            Log::error("Error in UpdateFederatedUsersMfaJob: " . $e->getMessage(), ['exception' => $e]);

            throw $e;
        }
    }
}
