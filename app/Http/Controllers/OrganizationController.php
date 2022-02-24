<?php

namespace App\Http\Controllers;

use App\Helpers\KeycloakHelper;
use App\Helpers\OrganizationHelper;
use App\Http\Resources\OrganizationResource;
use App\Models\Language;
use App\Models\Organization;
use App\Models\OrganizationKeycloakRealm;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;

define("KEYCLOAK_USERS", env('KEYCLOAK_URL') . '/auth/admin/realms/' . env('KEYCLOAK_REAMLS_NAME') . '/users');

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

        $user = User::create([
            'email' => $adminEmail,
            'first_name' => $name,
            'last_name' => $name,
            'type' => User::ADMIN_GROUP_ORG_ADMIN,
        ]);

        if (!$user) {
            return ['success' => false, 'message' => 'error_message.user_add'];
        }

        try {
            self::createKeycloakUser($user, $org, User::ADMIN_GROUP_ORG_ADMIN);
        } catch (\Exception $e) {
            DB::rollBack();
            return ['success' => false, 'message' => $e->getMessage()];
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
     * @param object $user
     * @param object $org
     * @param string $userGroup
     *
     * @return false|mixed|string
     * @throws \Exception
     */
    private function createKeycloakUser($user, $org, $userGroup)
    {
        $token = KeycloakHelper::getKeycloakAccessToken();
        if ($token) {
            try {
                $language = Language::find(Auth::user()->language_id);
                $languageCode = $language ? $language->code : '';
                $response = Http::withToken($token)->withHeaders([
                    'Content-Type' => 'application/json'
                ])->post(KEYCLOAK_USERS, [
                    'username' => $user->email,
                    'email' => $user->email,
                    'enabled' => true,
                    'firstName' => $user->first_name,
                    'lastName' => $user->last_name,
                    'attributes' => [
                        'locale' => [$languageCode]
                    ]
                ]);

                if ($response->successful()) {
                    $createdUserUrl = $response->header('Location');
                    $lintArray = explode('/', $createdUserUrl);
                    $userKeycloakUuid = end($lintArray);
                    $isCanSetPassword = true;
                    $isCanAssignUserToGroup = self::assignUserToGroup($token, $createdUserUrl, $userGroup);
                    if ($isCanSetPassword && $isCanAssignUserToGroup) {
                        return $userKeycloakUuid;
                    }
                }
            } catch (\Exception $e) {
                throw new \Exception($e->getMessage());
            }
        }
        throw new \Exception('no_token');
    }

    /**
     * @param string $token
     * @param string $userUrl
     * @param string $userGroup
     * @param false $isUnassigned
     *
     * @return bool
     */
    private static function assignUserToGroup($token, $userUrl, $userGroup, $isUnassigned = false)
    {
        $userGroups = KeycloakHelper::getUserGroups($token);
        $url = $userUrl . '/groups/' . $userGroups[$userGroup];
        if ($isUnassigned) {
            $response = Http::withToken($token)->delete($url);
        } else {
            $response = Http::withToken($token)->put($url);
        }
        if ($response->successful()) {
            return true;
        }
        return false;
    }

    /**
     * @param \Illuminate\Http\Request $request
     *
     * @return mixed
     */
    public function getOrganizationByName(Request $request)
    {
        return Organization::where('name', $request->get('orgName'))->firstOrFail();
    }

    /**
     * @param \Illuminate\Http\Request $request
     *
     * @return array
     */
    public function getTherapistAndTreatmentLimit(Request $request)
    {
        $orgName = $request->get('org_name');
        $org = Organization::where('name', $orgName)->firstOrFail();
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
        return Organization::where('status', Organization::ONGOING_ORG_STATUS)->firstOrFail();
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

            if ($status === Organization::SUCCESS_ORG_STATUS) {
                KeycloakHelper::sendEmailToNewUser(KeycloakHelper::getUser($organization->admin_email)[0]['id']);
            } else {
                OrganizationHelper::sendEmailNotification($organization->admin_email, $organization->name, $status);
            }

            return ['success' => true, 'message' => 'success_message.organization.update'];
        }

        return ['success' => false, 'message' => 'error_message.organization.update'];
    }
}
