<?php

namespace App\Http\Controllers;

use App\Models\Forwarder;
use App\Models\User;
use App\Models\MfaSetting;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Validation\Rule;
use App\Helpers\KeycloakHelper;
use App\Helpers\MfaSettingHelper;
use Illuminate\Support\Facades\Auth;
use App\Jobs\UpdateFederatedUsersMfaJob;
use App\Http\Resources\MfaSettingResource;
use App\Jobs\ReApplyMfaJob;
use App\Models\Clinic;
use App\Models\Country;
use App\Models\JobTracker;
use App\Models\Organization;
use App\Models\PhcService;
use App\Models\Region;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Http;

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

        $query = MfaSetting::where('created_by_role', $user->type);

        if (!in_array($user->type, [User::ADMIN_GROUP_SUPER_ADMIN, User::ADMIN_GROUP_ORG_ADMIN])) {
            $query->whereJsonContains('country_ids', $user->country_id);
        }

        $mfaSettings = $query->orderBy('created_at', 'desc')->get();

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
            $countryIds = collect($mfaSetting->country_ids ?? [])->filter(fn($id) => is_int($id) || is_string($id))->all();
            $regionIds = collect($mfaSetting->region_ids ?? [])->filter(fn($id) => is_int($id) || is_string($id))->all();
            $clinicIds = collect($mfaSetting->clinic_ids ?? [])->filter(fn($id) => is_int($id) || is_string($id))->all();
            $phcServiceIds = collect($mfaSetting->phc_service_ids ?? [])->filter(fn($id) => is_int($id) || is_string($id))->all();
            $organizationIds = collect($mfaSetting->organizations ?? [])->filter(fn($id) => is_int($id) || is_string($id))->all();

            $mfaSetting->countries = $countries->only($countryIds)->values();
            $mfaSetting->regions = $regions->only($regionIds)->values();
            $mfaSetting->clinics = $clinics->only($clinicIds)->values();
            $mfaSetting->phc_services = $phcServices->only($phcServiceIds)->values();
            $mfaSetting->organizations_name = $organizations->only($organizationIds)->values();

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

        if ($authUser->type === User::ADMIN_GROUP_CLINIC_ADMIN && MfaSetting::whereJsonContains('clinic_ids', $authUser->clinic_id)->count()) {
            return response()->json([
                'message' => 'mfa.mfa_setting.therapist.already_exist',
            ], 422);
        }

        if ($authUser->type === User::ADMIN_GROUP_PHC_SERVICE_ADMIN && MfaSetting::whereJsonContains('phc_service_ids', $authUser->phc_service_id)->count()) {
            return response()->json([
                'message' => 'mfa.mfa_setting.phc_worker.already_exist',
            ], 422);
        }

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
            $validatedData['region_ids'] = $authUser->regions->pluck('id');
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

        $jobId = Str::uuid()->toString();

        JobTracker::create([
            'job_id' => $jobId,
            'status' => JobTracker::QUEUED,
            'trackable_type' => MfaSetting::class,
            'trackable_id' => $newMfaSetting->id,
        ]);

        UpdateFederatedUsersMfaJob::dispatch($jobId, $newMfaSetting, $authUser);

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
            $validatedData['region_ids'] = $authUser->regions->pluck('id');
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

        $jobId = Str::uuid()->toString();

        JobTracker::create([
            'job_id' => $jobId,
            'status' => JobTracker::QUEUED,
            'trackable_type' => MfaSetting::class,
            'trackable_id' => $mfaSetting->id,
        ]);

        UpdateFederatedUsersMfaJob::dispatch($jobId, $mfaSetting, $authUser);

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

    public function destroy(MfaSetting $mfaSetting)
    {
        $authUser = Auth::user();

        $jobId = Str::uuid()->toString();

        JobTracker::create([
            'job_id' => $jobId,
            'status' => JobTracker::QUEUED,
            'trackable_type' => MfaSetting::class,
            'trackable_id' => $mfaSetting->id,
        ]);

        ReApplyMfaJob::dispatch($jobId, $mfaSetting, $authUser);

        return response()->json(['message' => 'mfa.delete.success']);
    }
}
