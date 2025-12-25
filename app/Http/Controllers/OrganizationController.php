<?php

namespace App\Http\Controllers;

use App\Helpers\LimitationHelper;
use App\Helpers\OrganizationHelper;
use App\Http\Resources\OrganizationResource;
use App\Models\Organization;
use Illuminate\Http\Request;

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
        $validatedData = $request->validate([
            'name' => 'required|string|unique:organizations,name',
            'admin_email' => 'required|email|unique:users,email',
            'sub_domain_name' => 'required|string',
            'max_number_of_therapist' => 'required|integer|min:0',
            'max_number_of_phc_worker' => 'required|integer|min:0',
            'max_ongoing_treatment_plan' => 'required|integer|min:0',
            'max_phc_ongoing_treatment_plan' => 'required|integer|min:0',
            'max_sms_per_week' => 'required|integer|min:0',
            'max_phc_sms_per_week' => 'integer|min:0',
        ], [
            'name.unique' => 'error_message.organization_exists',
            'admin_email.unique' => 'error_message.email_exists',
            'sub_domain_name.unique' => 'error_message.organization_sub_domain_exists',
        ]);

        $validatedData['type'] = Organization::NON_HI_TYPE;
        $validatedData['status'] = Organization::ONGOING_ORG_STATUS;

        $org = Organization::create($validatedData);

        if (!$org) {
            return ['success' => false, 'message' => 'error_message.organization_add'];
        }

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
        $validatedData = $request->validate([
            'name' => 'required|string|unique:organizations,name,' . $organization->id,
            'max_number_of_therapist' => 'required|integer|min:0',
            'max_number_of_phc_worker' => 'required|integer|min:0',
            'max_ongoing_treatment_plan' => 'required|integer|min:0',
            'max_phc_ongoing_treatment_plan' => 'required|integer|min:0',
            'max_sms_per_week' => 'required|integer|min:0',
            'max_phc_sms_per_week' => 'integer|min:0',
        ], [
            'name.unique' => 'error_message.organization_exists'
        ]);

        $hiOrganization = Organization::where('sub_domain_name', env('APP_NAME'))->firstOrFail();

        if ($organization->id === $hiOrganization->id) {
            $orgLimitation = LimitationHelper::orgLimitation();

            if ($orgLimitation['therapist_limit_used'] > $validatedData['max_number_of_therapist']) {
                abort(422, 'error.organization.max_number_of_therapist.less_than.country.total.therapist_limit');
            }

            if ($orgLimitation['phc_worker_limit_used'] > $validatedData['max_number_of_phc_worker']) {
                abort(422, 'error.organization.max_number_of_phc_worker.less_than.country.total.phc_worker_limit');
            }
        }

        $organization->update($validatedData);

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
        $org = Organization::where('sub_domain_name', $request->get('org_name'))->firstOrFail();

        return [
            'success' => true,
            'data' => [
                'max_therapist' => $org->max_number_of_therapist,
                'max_ongoing_treatment_plan' => $org->max_ongoing_treatment_plan,
                'max_phc_ongoing_treatment_plan' => $org->max_phc_ongoing_treatment_plan,
            ],
        ];
    }

    /**
     * @param \Illuminate\Http\Request $request
     *
     * @return array
     */
    public function getTherapistAndMaxSms(Request $request)
    {
        $org = Organization::where('sub_domain_name', $request->get('org_name'))->firstOrFail();

        return [
            'success' => true,
            'data' => [
                'max_therapist' => $org->max_number_of_therapist,
                'max_sms_per_week' => $org->max_sms_per_week,
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

            return ['success' => true, 'message' => 'success_message.organization.update'];
        }

        return ['success' => false, 'message' => 'error_message.organization.update'];
    }

    /**
     * Get the remaining limit for a the country.
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function limitation()
    {
        return response()->json(['data' => LimitationHelper::orgLimitation(), 200]);
    }
}
