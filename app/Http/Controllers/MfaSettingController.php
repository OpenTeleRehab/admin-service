<?php

namespace App\Http\Controllers;

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
        $configurations = MfaSetting::orderBy('created_at', 'desc')->get();

        return [
            'success' => true,
            'data' => MfaSettingResource::collection($configurations),
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
     * Dispatch a job to update Keycloak user attributes and track it.
     *
     * @param array<string, mixed> $data
     * @param int $mfaSettingId
     * @return void
     */
    private function dispatchUpdateJob(array $data, int $mfaSettingId)
    {
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

        UpdateKeycloakUserAttributes::dispatch($data, $jobId);
    }
}
