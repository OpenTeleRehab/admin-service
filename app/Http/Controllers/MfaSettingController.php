<?php

namespace App\Http\Controllers;

use App\Helpers\KeycloakHelper;
use App\Http\Resources\MfaSettingResource;
use App\Jobs\UpdateKeycloakUserAttributes;
use App\Models\JobTracker;
use App\Models\MfaSetting;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Auth;

class MfaSettingController extends Controller
{
    /**
     * Display a listing of the MFA configurations.
     *
     * @return array<string, mixed>
     */
    public function index(): array
    {
        $user = Auth::user();

        $mfaSettings = MfaSetting::query();

        switch ($user->type) {
            case User::ADMIN_GROUP_SUPER_ADMIN:
                $roles = [
                    User::ADMIN_GROUP_ORG_ADMIN,
                    User::ADMIN_GROUP_COUNTRY_ADMIN,
                    User::ADMIN_GROUP_CLINIC_ADMIN,
                    User::GROUP_THERAPIST
                ];
                $mfaSettings->whereHas('user', fn($query) => $query->where('type', $user->type));
                break;

            case User::ADMIN_GROUP_ORG_ADMIN:
                $roles = [
                    User::ADMIN_GROUP_COUNTRY_ADMIN,
                    User::ADMIN_GROUP_CLINIC_ADMIN,
                    User::GROUP_THERAPIST
                ];
                $mfaSettings->whereHas('user', fn($query) => $query->where('type', $user->type));
                break;

            case User::ADMIN_GROUP_COUNTRY_ADMIN:
                $roles = [
                    User::ADMIN_GROUP_CLINIC_ADMIN,
                    User::GROUP_THERAPIST
                ];
                $mfaSettings->whereHas('user', fn($query) => 
                    $query->where('type', $user->type)
                        ->where('country_id', $user->country_id)
                );
                break;

            case User::ADMIN_GROUP_CLINIC_ADMIN:
                $roles = [
                    User::GROUP_THERAPIST
                ];
                $mfaSettings->whereHas('user', fn($query) => 
                    $query->where('type', $user->type)
                        ->where('country_id', $user->country_id)
                        ->where('clinic_id', $user->clinic_id)
                );
                break;
            default:
                $roles = [];
                break;
        }

        $mfaSettingsCollection = $mfaSettings->whereIn('role', $roles)
                                            ->orderBy('created_at', 'desc')
                                            ->get();

        return [
            'success' => true,
            'data' => MfaSettingResource::collection($mfaSettingsCollection),
        ];
    }

    /**
     * Queue a job to update admin attributes in Keycloak.
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function store(Request $request)
    {
        $authUser = Auth::user();
        $data = $request->validate([
            'role' => 'required|string',
            'organizations' => 'sometimes|array|nullable',
            'country_ids' => 'sometimes|array|nullable',
            'clinic_ids' => 'sometimes|array|nullable',
            'attributes' => 'required|array',
        ]);

        if (
            $authUser->type === User::ADMIN_GROUP_COUNTRY_ADMIN &&
            (
                empty($data['country_ids']) ||
                !in_array($authUser->country_id, $data['country_ids'])
            )
        ) {
            abort(409, 'error_message.mfa_setting_country_invalid');
        }

        $mfaSetting = MfaSetting::create($data);

        $this->dispatchUpdateJob($data, $mfaSetting->id);

        return ['success' => true, 'message' => 'MFA setting has been created successfully.'];
    }

    /**
     * Queue a job to update admin attributes in Keycloak.
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function update(Request $request, MfaSetting $mfaSetting)
    {
        $authUser = Auth::user();
        $data = $request->validate([
            'role' => 'required|string',
            'organizations' => 'sometimes|array|nullable',
            'country_ids' => 'sometimes|array|nullable',
            'clinic_ids' => 'sometimes|array|nullable',
            'attributes' => 'required|array',
        ]);

        if (
            $authUser->type === User::ADMIN_GROUP_COUNTRY_ADMIN &&
            (
                empty($data['country_ids']) ||
                !in_array($authUser->country_id, $data['country_ids'])
            )
        ) {
            abort(409, 'error_message.mfa_setting_country_invalid');
        }

        $mfaSetting->update($data);

        $this->dispatchUpdateJob($data, $mfaSetting->id);

        return ['success' => true, 'message' => 'MFA setting has been updated successfully.'];
    }

    /**
     * Retrieve the mfa settings resources.
     *
     * @return array
     */
    public function getMfaSettingsUserResources(): array
    {
        $user = Auth::user();

        $userData = KeycloakHelper::getKeycloakUserByUsername($user->email);

        $mfaSettings = MfaSetting::query();

        switch ($user->type) {
            case User::ADMIN_GROUP_SUPER_ADMIN:
                $roles = [
                    User::ADMIN_GROUP_ORG_ADMIN,
                    User::ADMIN_GROUP_COUNTRY_ADMIN,
                    User::ADMIN_GROUP_CLINIC_ADMIN,
                    User::GROUP_THERAPIST
                ];
                $mfaSettings->whereHas('user', fn($query) => $query->where('type', $user->type));
                break;

            case User::ADMIN_GROUP_ORG_ADMIN:
                $roles = [
                    User::ADMIN_GROUP_COUNTRY_ADMIN,
                    User::ADMIN_GROUP_CLINIC_ADMIN,
                    User::GROUP_THERAPIST
                ];
                $mfaSettings->whereHas('user', fn($query) => $query->where('type', $user->type));
                break;

            case User::ADMIN_GROUP_COUNTRY_ADMIN:
                $roles = [
                    User::ADMIN_GROUP_CLINIC_ADMIN,
                    User::GROUP_THERAPIST
                ];
                $mfaSettings->whereHas('user', fn($query) => 
                    $query->where('type', $user->type)
                        ->where('country_id', $user->country_id)
                );
                break;

            case User::ADMIN_GROUP_CLINIC_ADMIN:
                $roles = [
                    User::GROUP_THERAPIST
                ];
                $mfaSettings->whereHas('user', fn($query) => 
                    $query->where('type', $user->type)
                        ->where('country_id', $user->country_id)
                        ->where('clinic_id', $user->clinic_id)
                );
                break;
            default:
                $roles = [];
                break;
        }

        $mfaSettings->whereIn('role', $roles);

        $settings = $mfaSettings->with('user')->get();

        $usedIds = [
            'organization_admin' => ['used_clinic_ids' => [], 'used_country_ids' => []],
            'country_admin' => ['used_clinic_ids' => [], 'used_country_ids' => []],
            'clinic_admin' => ['used_clinic_ids' => [], 'used_country_ids' => []],
            'therapist' => ['used_clinic_ids' => [], 'used_country_ids' => []],
        ];

        foreach ($settings as $setting) {
            $roleKey = match ($setting->role) {
                User::ADMIN_GROUP_ORG_ADMIN => 'organization_admin',
                User::ADMIN_GROUP_COUNTRY_ADMIN => 'country_admin',
                User::ADMIN_GROUP_CLINIC_ADMIN => 'clinic_admin',
                User::GROUP_THERAPIST => 'therapist',
                default => null,
            };

            if ($roleKey) {
                $usedIds[$roleKey]['used_clinic_ids'] = array_merge(
                    $usedIds[$roleKey]['used_clinic_ids'],
                    $setting->clinic_ids ?? []
                );

                $usedIds[$roleKey]['used_country_ids'] = array_merge(
                    $usedIds[$roleKey]['used_country_ids'],
                    $setting->country_ids ?? []
                );
            }
        }

        foreach ($usedIds as &$role) {
            $role['used_clinic_ids'] = array_unique($role['used_clinic_ids']);
            $role['used_country_ids'] = array_unique($role['used_country_ids']);
        }

        return [
            'success' => true,
            'data' => [
                'user_attributes' => $userData['attributes'] ?? [],
                'used_ids' => $usedIds,
            ],
        ];
    }

    /**
     * Dispatch a job to update Keycloak user attributes and track it.
     *
     * @param array<string, mixed> $data
     * @param int $mfaSettingId
     * @return void
     */
    private function dispatchUpdateJob(array $data, int $mfaSettingId)
    {
        $authId = Auth::id();
        $jobId = (string) Str::uuid();

        JobTracker::updateOrCreate(
            ['job_id' => $jobId],
            [
                'status' => JobTracker::RUNNING,
                'trackable_type' => MfaSetting::class,
                'trackable_id' => $mfaSettingId,
                'updated_at' => now(),
            ]
        );

        UpdateKeycloakUserAttributes::dispatch($data, $jobId, $authId);
    }
}
