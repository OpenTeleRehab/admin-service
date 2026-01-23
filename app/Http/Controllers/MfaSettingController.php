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
use App\Models\Clinic;
use App\Models\Country;
use App\Models\Organization;
use App\Models\PhcService;
use App\Models\Region;

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

        $allClinicIds = collect($mfaSettings)->pluck('clinic_ids')->flatten()->unique();
        $allCountryIds = collect($mfaSettings)->pluck('country_ids')->flatten()->unique();
        $allRegionIds = collect($mfaSettings)->pluck('region_ids')->flatten()->unique();
        $allPhcServiceIds = collect($mfaSettings)->pluck('phc_service_ids')->flatten()->unique();
        $allOrganizationIds = collect($mfaSettings)->pluck('organizations')->flatten()->unique();

        $clinics = Clinic::whereIn('id', $allClinicIds)->pluck('name', 'id');
        $countries = Country::whereIn('id', $allCountryIds)->pluck('name', 'id');
        $regions = Region::whereIn('id', $allRegionIds)->pluck('name', 'id');
        $phcServices = PhcService::whereIn('id', $allPhcServiceIds)->pluck('name', 'id');
        $organizations = Organization::whereIn('id', $allOrganizationIds)->pluck('name', 'id');

        $mfaSettings = $mfaSettings->map(function ($mfaSetting) use ($clinics, $countries, $regions, $phcServices, $organizations) {
            $mfaSetting->countries = $mfaSetting->country_ids ? $countries->only($mfaSetting->country_ids)->values() : [];
            $mfaSetting->regions = $mfaSetting->region_ids ? $regions->only($mfaSetting->region_ids)->values() : [];
            $mfaSetting->clinics = $mfaSetting->clinic_ids ? $clinics->only($mfaSetting->clinic_ids)->values() : [];
            $mfaSetting->phc_services = $mfaSetting->phc_service_ids ? $phcServices->only($mfaSetting->phc_service_ids)->values() : [];
            $mfaSetting->organizations_name = $mfaSetting->organizations ? $organizations->only($mfaSetting->organizations)->values() : [];

            return $mfaSetting;
        });

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
            'region_ids' => [
                Rule::requiredIf($authUser->type === User::ADMIN_GROUP_COUNTRY_ADMIN && $role === User::ADMIN_GROUP_REGIONAL_ADMIN),
                'array',
            ],
            'clinic_ids' => [
                Rule::requiredIf($authUser->type === User::ADMIN_GROUP_REGIONAL_ADMIN && ($role === User::ADMIN_GROUP_CLINIC_ADMIN || $role === User::GROUP_THERAPIST)),
                'array',
            ],
            'phc_service_ids' => [
                Rule::requiredIf($authUser->type === User::ADMIN_GROUP_REGIONAL_ADMIN && ($role === User::ADMIN_GROUP_PHC_SERVICE_ADMIN || $role === User::GROUP_PHC_WORKER)),
                'array',
            ],
            'mfa_enforcement' => 'required|in:skip,recommend,force',
            'mfa_expiration_duration' => 'nullable|integer|min:0',
            'skip_mfa_setup_duration' => 'nullable|integer|min:0',
            'mfa_expiration_unit' => 'nullable|string',
            'skip_mfa_setup_unit' => 'nullable|string',
        ]);

        if ($validatedData['mfa_enforcement'] === MfaSetting::MFA_DISABLE) {
            $validatedData['mfa_expiration_duration'] = null;
            $validatedData['skip_mfa_setup_duration'] = null;
            $validatedData['mfa_expiration_unit'] = null;
            $validatedData['skip_mfa_setup_unit'] = null;
        } else if ($validatedData['mfa_enforcement'] === MfaSetting::MFA_ENFORCE) {
            $validatedData['skip_mfa_setup_duration'] = null;
            $validatedData['skip_mfa_setup_unit'] = null;
        }

        $mfaSettingAboutRole = MfaSettingHelper::getMfaSettingAboveRole($authUser, $validatedData['role']);

        if (!MfaSettingHelper::validateMfaEnforcement($mfaSettingAboutRole, $validatedData['mfa_enforcement'])) {
            return response()->json([
                'message' => 'mfa.enforcement.cannot.weaker.than.parent.role',
            ], 422);
        }

        if ($authUser->type === User::ADMIN_GROUP_SUPER_ADMIN) {
            $validatedData['country_ids'] = null;
            $validatedData['region_ids'] = null;
            $validatedData['clinic_ids'] = null;
            $validatedData['phc_service_ids'] = null;
        } else if ($authUser->type === User::ADMIN_GROUP_ORG_ADMIN) {
            $validatedData['region_ids'] = null;
            $validatedData['clinic_ids'] = null;
            $validatedData['phc_service_ids'] = null;
        } else if ($authUser->type === User::ADMIN_GROUP_COUNTRY_ADMIN) {
            $validatedData['country_ids'] = [$authUser->country_id];
            $validatedData['clinic_ids'] = null;
            $validatedData['phc_service_ids'] = null;
        } else if ($authUser->type === User::ADMIN_GROUP_REGIONAL_ADMIN) {
            $validatedData['country_ids'] = [$authUser->country_id];
            $validatedData['region_ids'] = [$authUser->region_id];
        } else if ($authUser->type === User::ADMIN_GROUP_CLINIC_ADMIN) {
            $validatedData['country_ids'] = [$authUser->country_id];
            $validatedData['region_ids'] = [$authUser->region_id];
            $validatedData['clinic_ids'] = [$authUser->clinic_id];
            $validatedData['phc_service_ids'] = null;
        } else if ($authUser->type === User::ADMIN_GROUP_PHC_SERVICE_ADMIN) {
            $validatedData['country_ids'] = [$authUser->country_id];
            $validatedData['region_ids'] = [$authUser->region_id];
            $validatedData['clinic_ids'] = null;
            $validatedData['phc_service_ids'] = [$authUser->phc_service_id];
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
            'region_ids' => [
                Rule::requiredIf($authUser->type === User::ADMIN_GROUP_COUNTRY_ADMIN && $role === User::ADMIN_GROUP_REGIONAL_ADMIN),
                'array',
            ],
            'clinic_ids' => [
                Rule::requiredIf($authUser->type === User::ADMIN_GROUP_REGIONAL_ADMIN && ($role === User::ADMIN_GROUP_CLINIC_ADMIN || $role === User::GROUP_THERAPIST)),
                'array',
            ],
            'phc_service_ids' => [
                Rule::requiredIf($authUser->type === User::ADMIN_GROUP_REGIONAL_ADMIN && ($role === User::ADMIN_GROUP_PHC_SERVICE_ADMIN || $role === User::GROUP_PHC_WORKER)),
                'array',
            ],
            'mfa_enforcement' => 'required|in:skip,recommend,force',
            'mfa_expiration_duration' => 'nullable|integer|min:0',
            'skip_mfa_setup_duration' => 'nullable|integer|min:0',
            'mfa_expiration_unit' => 'nullable|string',
            'skip_mfa_setup_unit' => 'nullable|string',
        ]);

        if ($validatedData['mfa_enforcement'] === MfaSetting::MFA_DISABLE) {
            $validatedData['mfa_expiration_duration'] = null;
            $validatedData['skip_mfa_setup_duration'] = null;
            $validatedData['mfa_expiration_unit'] = null;
            $validatedData['skip_mfa_setup_unit'] = null;
        } else if ($validatedData['mfa_enforcement'] === MfaSetting::MFA_ENFORCE) {
            $validatedData['skip_mfa_setup_duration'] = null;
            $validatedData['skip_mfa_setup_unit'] = null;
        }

        $parentSetting = MfaSettingHelper::getMfaSettingAboveRole($authUser, $validatedData['role']);

        if (!MfaSettingHelper::validateMfaEnforcement($parentSetting, $validatedData['mfa_enforcement'])) {
            return response()->json([
                'message' => 'mfa.enforcement.cannot.weaker.than.parent.role',
            ], 422);
        }

        if ($authUser->type === User::ADMIN_GROUP_SUPER_ADMIN) {
            $validatedData['country_ids'] = null;
            $validatedData['region_ids'] = null;
            $validatedData['clinic_ids'] = null;
            $validatedData['phc_service_ids'] = null;
        } else if ($authUser->type === User::ADMIN_GROUP_ORG_ADMIN) {
            $validatedData['region_ids'] = null;
            $validatedData['clinic_ids'] = null;
            $validatedData['phc_service_ids'] = null;
        } else if ($authUser->type === User::ADMIN_GROUP_COUNTRY_ADMIN) {
            $validatedData['country_ids'] = [$authUser->country_id];
            $validatedData['clinic_ids'] = null;
            $validatedData['phc_service_ids'] = null;
        } else if ($authUser->type === User::ADMIN_GROUP_REGIONAL_ADMIN) {
            $validatedData['country_ids'] = [$authUser->country_id];
            $validatedData['region_ids'] = [$authUser->region_id];
        } else if ($authUser->type === User::ADMIN_GROUP_CLINIC_ADMIN) {
            $validatedData['country_ids'] = [$authUser->country_id];
            $validatedData['region_ids'] = [$authUser->region_id];
            $validatedData['clinic_ids'] = [$authUser->clinic_id];
            $validatedData['phc_service_ids'] = null;
        } else if ($authUser->type === User::ADMIN_GROUP_PHC_SERVICE_ADMIN) {
            $validatedData['country_ids'] = [$authUser->country_id];
            $validatedData['region_ids'] = [$authUser->region_id];
            $validatedData['clinic_ids'] = null;
            $validatedData['phc_service_ids'] = [$authUser->phc_service_id];
        }

        $mfaSetting->update($validatedData);

        $job = new UpdateFederatedUsersMfaJob($mfaSetting, $authUser);

        dispatch($job);

        return ['success' => true, 'message' => 'mfa.setting.success_message.update'];
    }

    public function validateMfaEnforcementAgainstHigherRole(Request $request)
    {
        $validatedData = $request->validate([
            'role' => 'required|in:phc_worker,therapist,phc_service_admin,clinic_admin,regional_admin,country_admin,organization_admin,super_admin,translator',
        ]);

        $authUser = Auth::user();

        $mfaSettingAboutRole = MfaSettingHelper::getMfaSettingAboveRole($authUser, $validatedData['role']);

        return response()->json(['success' => true, 'data' => $mfaSettingAboutRole?->mfa_enforcement], 200);
    }
}
