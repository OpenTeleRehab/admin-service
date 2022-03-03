<?php

namespace App\Http\Controllers;

use App\Helpers\KeycloakHelper;
use App\Helpers\OrganizationHelper;
use App\Http\Resources\OrganizationResource;
use App\Models\Organization;
use App\Models\OrganizationKeycloakRealm;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class OrganizationController extends Controller
{
    /**
     * @return array
     */
    public function index()
    {
        $organizations = Organization::all();

        return ['success' => true, 'data' => OrganizationResource::collection($organizations)];
    }

    /**
     * @param \App\Models\Organization $organization
     *
     * @return \App\Http\Resources\OrganizationResource
     */
    public function show(Organization $organization)
    {
        return new OrganizationResource($organization);
    }

    /**
     * @param \Illuminate\Http\Request $request
     *
     * @return array
     */
    public function store(Request $request)
    {
        DB::beginTransaction();
        $name = $request->get('name');
        $adminEmail = $request->get('admin_email');
        $subDomainName = $request->get('sub_domain_name');
        $maxNumberOfTherapist = $request->get('max_number_of_therapist');
        $maxOngoingTreatmentPlan = $request->get('max_ongoing_treatment_plan');

        $availableEmail = User::where('email', $adminEmail)->count();

        if ($availableEmail) {
            return abort(409, 'error_message.email_exists');
        }

        $existOrganization = Organization::where('name', $name)->count();

        if ($existOrganization) {
            return abort(409, 'error_message.organization_exists');
        }

        $existSubDomain = Organization::where('sub_domain_name', $subDomainName)->count();

        if ($existSubDomain) {
            return abort(409, 'error_message.organization_sub_domain_exists');
        }

        $org = Organization::create([
            'name' => $name,
            'type' => Organization::NON_HI_TYPE,
            'admin_email' => $adminEmail,
            'sub_domain_name' => $subDomainName,
            'max_number_of_therapist' => $maxNumberOfTherapist,
            'max_ongoing_treatment_plan' => $maxOngoingTreatmentPlan,
            'status' => Organization::ONGOING_ORG_STATUS,
            'created_by' => Auth::id()
        ]);

        if (!$org) {
            return ['success' => false, 'message' => 'error_message.organization_add'];
        }

        DB::commit();

        return ['success' => true, 'message' => 'success_message.organization_add'];
    }

    /**
     * @param \Illuminate\Http\Request $request
     * @param \App\Models\Organization $organization
     *
     * @return array
     */
    public function update(Request $request, Organization $organization)
    {
        $organization->update([
            'max_number_of_therapist' => $request->get('max_number_of_therapist'),
            'max_ongoing_treatment_plan' => $request->get('max_ongoing_treatment_plan'),
        ]);

        return ['success' => true, 'message' => 'success_message.organization.update'];
    }

    /**
     * @param \App\Models\Organization $organization
     *
     * @return array
     * @throws \Exception
     */
    public function destroy(Organization $organization)
    {
        $organization->delete();
        return ['success' => true, 'message' => 'success_message.organization_delete'];
    }

    /**
     * @param \Illuminate\Http\Request $request
     *
     * @return mixed
     */
    public function getOrganization(Request $request)
    {
        return Organization::where('sub_domain_name', $request->get('sub_domain'))->firstOrFail();
    }

    /**
     * @param \Illuminate\Http\Request $request
     *
     * @return array
     */
    public function getTherapistAndTreatmentLimit(Request $request)
    {
        $subDomain = $request->get('sub_domain');
        $org = Organization::where('sub_domain_name', $subDomain)->firstOrFail();
        return [
            'success' => true,
            'data' => [
                'max_therapist' => $org->max_number_of_therapist,
                'max_ongoing_treatment_plan' => $org->max_ongoing_treatment_plan,
            ],
        ];
    }

    /**
     * @return mixed
     */
    public function getOngoingOrganization()
    {
        return Organization::where('status', Organization::ONGOING_ORG_STATUS)->first();
    }

    /**
     * @param \Illuminate\Http\Request $request
     *
     * @return array
     */
    public function updateOrganizationStatus(Request $request)
    {
        $status = $request->get('status');
        $email = $request->get('email');

        $organization = Organization::where('admin_email', $email)->first();

        if ($organization) {
            Organization::where('admin_email', $email)->update(['status' => $status]);

            OrganizationHelper::sendEmailNotification($organization->admin_email, $organization->name, $status);

            if ($status === Organization::SUCCESS_ORG_STATUS) {
                KeycloakHelper::sendEmailToNewUser(KeycloakHelper::getUser($organization->admin_email)[0]['id']);
            }

            return ['success' => true, 'message' => 'success_message.organization.update'];
        }

        return ['success' => false, 'message' => 'error_message.organization.update'];
    }
}
