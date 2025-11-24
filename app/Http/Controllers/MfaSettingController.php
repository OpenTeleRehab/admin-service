<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Models\MfaSetting;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use App\Helpers\MfaSettingHelper;
use Illuminate\Support\Facades\Auth;
use App\Jobs\UpdateFederatedUsersMfaJob;
use App\Http\Resources\MfaSettingResource;

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

        $mfaSettings = MfaSetting::where('created_by_role', $user->type)
            ->orderBy('created_at', 'desc')
            ->get();

        return [
            'success' => true,
            'data' => MfaSettingResource::collection($mfaSettings),
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
        $role = $request->input('role');
        $validatedData = $request->validate([
            'role' => 'required|string',
            'organizations' => [
                Rule::requiredIf($authUser->type === User::ADMIN_GROUP_SUPER_ADMIN && $role === User::ADMIN_GROUP_ORG_ADMIN),
                'array',
            ],
            'country_ids' => [
                Rule::requiredIf($authUser->type === User::ADMIN_GROUP_ORG_ADMIN && $role === User::ADMIN_GROUP_COUNTRY_ADMIN),
                'array',
            ],
            'clinic_ids' => [
                Rule::requiredIf($authUser->type === User::ADMIN_GROUP_COUNTRY_ADMIN),
                'array',
            ],
            'mfa_enforcement' => 'required|in:skip,recommend,force',
            'mfa_expiration_duration' => 'nullable|integer|min:0',
            'skip_mfa_setup_duration' => 'nullable|integer|min:0',
        ]);

        if ($validatedData['mfa_enforcement'] === MfaSetting::MFA_DISABLE) {
            $validatedData['mfa_expiration_duration'] = null;
            $validatedData['skip_mfa_setup_duration'] = null;
        } else if ($validatedData['mfa_enforcement'] === MfaSetting::MFA_ENFORCE) {
            $validatedData['skip_mfa_setup_duration'] = null;
        }

        $mfaSettingAboutRole = MfaSettingHelper::getMfaSettingAboveRole($authUser, $validatedData['role']);

        if (!MfaSettingHelper::validateMfaEnforcement($mfaSettingAboutRole, $validatedData['mfa_enforcement'])) {
            return response()->json([
                'message' => 'mfa.enforcement.cannot.weaker.than.parent.role',
            ], 422);
        }

        if ($authUser->type === User::ADMIN_GROUP_SUPER_ADMIN) {
            $validatedData['country_ids'] = null;
            $validatedData['clinic_ids'] = null;
        } else if ($authUser->type === User::ADMIN_GROUP_ORG_ADMIN) {
            $validatedData['clinic_ids'] = null;
        }

        $newMfaSetting = MfaSetting::create($validatedData);

        $job = new UpdateFederatedUsersMfaJob($newMfaSetting, $authUser);

        dispatch($job);

        return ['success' => true, 'message' => 'mfa.setting.success_message.create'];
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
        $role = $request->input('role');

        $validatedData = $request->validate([
            'role' => 'required|string',
            'organizations' => [
                Rule::requiredIf($authUser->type === User::ADMIN_GROUP_SUPER_ADMIN && $role === User::ADMIN_GROUP_ORG_ADMIN),
                'array',
            ],
            'country_ids' => [
                Rule::requiredIf($authUser->type === User::ADMIN_GROUP_ORG_ADMIN && $role === User::ADMIN_GROUP_COUNTRY_ADMIN),
                'array',
            ],
            'clinic_ids' => [
                Rule::requiredIf($authUser->type === User::ADMIN_GROUP_COUNTRY_ADMIN),
                'array',
            ],
            'mfa_enforcement' => 'required|in:skip,recommend,force',
            'mfa_expiration_duration' => 'nullable|integer|min:0',
            'skip_mfa_setup_duration' => 'nullable|integer|min:0',
        ]);

        if ($validatedData['mfa_enforcement'] === MfaSetting::MFA_DISABLE) {
            $validatedData['mfa_expiration_duration'] = null;
            $validatedData['skip_mfa_setup_duration'] = null;
        } else if ($validatedData['mfa_enforcement'] === MfaSetting::MFA_ENFORCE) {
            $validatedData['skip_mfa_setup_duration'] = null;
        }

        $parentSetting = MfaSettingHelper::getMfaSettingAboveRole($authUser, $validatedData['role']);

        if (!MfaSettingHelper::validateMfaEnforcement($parentSetting, $validatedData['mfa_enforcement'])) {
            return response()->json([
                'message' => 'mfa.enforcement.cannot.weaker.than.parent.role',
            ], 422);
        }

        $mfaSetting->update($validatedData);

        $job = new UpdateFederatedUsersMfaJob($mfaSetting, $authUser);

        dispatch($job);

        return ['success' => true, 'message' => 'mfa.setting.success_message.update'];
    }

    public function validateMfaEnforcementAgainstHigherRole(Request $request)
    {
        $validatedData = $request->validate([
            'role' => 'required|in:therapist,clinic_admin,country_admin,organization_admin,super_admin',
        ]);

        $authUser = Auth::user();

        $mfaSettingAboutRole = MfaSettingHelper::getMfaSettingAboveRole($authUser, $validatedData['role']);

        return response()->json(['success' => true, 'data' => $mfaSettingAboutRole?->mfa_enforcement], 200);
    }
}
