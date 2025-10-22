<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Helpers\KeycloakHelper;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;

class SetupKeycloakPermissions extends Command
{
    protected $signature = 'hi:keycloak-setup-permissions';
    protected $description = 'Create Keycloak roles and assign them to groups';

    public function handle()
    {
        $groups = ['library_admin'];
        $roles = [
            'view_profession',
            'view_clinic_list',
            'import_exercise',
            'import_disease',
            'file_upload',
            'manage_download_tracker',
            'view_organization',
            'view_exercise_list',
            'view_educational_material_list',
            'view_system_limit_list',
            'view_term_condition_list',
            'view_term_condition',
            'view_privacy_policy_list',
            'view_privacy_policy',
            'submit_survey',
            'skip_survey',
            'manage_global_patient',
            'manage_global_at_patient',
            'view_number_of_clinic_therapist',
            'view_language',
            'view_clinic_therapist_list',
            'delete_therapist',
            'view_therapist_list',
            'view_transfer_list_by_therapist',
            'view_therapist_patient_list',
            'view_remove_therapist_patient',
            'view_patient_treatment_plan',
            'view_patient_treatment_plan_detail',
            'manage_own_profile',
            'view_default_limited_patient',
            'view_category_tree',
            'view_dashboard',
            'generate_report',
            'view_audit_log',
            'manage_therapist',
            'manage_patient',
            'view_country_therapist_limit',
            'view_patient_list_by_therapist_ids',
            // Role for hi-library
            'get_library_category',
            'get_educational_material',
            'get_educational_material_file',
            'get_educational_material_category',
            'get_library_exercise',
            'get_exercise_file',
            'get_exercise_additional_field',
            'get_exercise_category',
            'get_library_questionnaire',
            'get_questionnaire_question',
            'get_question_file',
            'get_question_answer',
            'get_questionnaire_category',
        ];
        $groupRoles = [
            'super_admin' => [
                'view_profession',
                'view_clinic_list',
                'view_default_limited_patient',
                'view_category_tree',
                'manage_own_profile',
                'import_exercise',
                'import_disease',
                'file_upload',
                'view_audit_log',
                'manage_download_tracker',
                'generate_report',
                'access_all',
            ],
            'organization_admin' => [
                'view_profession',
                'view_organization',
                'view_country_therapist_limit',
                'view_clinic_list',
                'view_exercise_list',
                'view_educational_material_list',
                'view_default_limited_patient',
                'view_system_limit_list',
                'view_term_condition_list',
                'view_term_condition',
                'view_privacy_policy_list',
                'view_privacy_policy',
                'submit_survey',
                'skip_survey',
                'manage_own_profile',
                'manage_global_patient',
                'view_clinic_therapist_list',
                'delete_therapist',
                'view_therapist_list',
                'view_transfer_list_by_therapist',
                'view_patient_list_by_therapist_ids',
                'view_remove_therapist_patient',
                'view_patient_treatment_plan',
                'manage_global_at_patient',
                'view_audit_log',
                'view_dashboard',
                'manage_download_tracker',
                'generate_report',
                'view_category_tree',
                'view_language',
                'view_therapist_patient_list',
                'view_patient_treatment_plan_detail',
                'access_all',
            ],
            'country_admin' => [
                'view_organization',
                'view_default_limited_patient',
                'submit_survey',
                'skip_survey',
                'manage_own_profile',
                'manage_global_patient',
                'view_patient_treatment_plan',
                'view_patient_treatment_plan_detail',
                'manage_global_at_patient',
                'view_audit_log',
                'view_dashboard',
                'manage_download_tracker',
                'generate_report',
                'view_language',
                'view_country_therapist_limit',
                'view_number_of_clinic_therapist',
                'view_exercise',
                'access_all',
            ],
            'clinic_admin' => [
                'view_profession',
                'view_organization',
                'view_clinic_list',
                'view_default_limited_patient',
                'submit_survey',
                'skip_survey',
                'manage_own_profile',
                'manage_global_patient',
                'manage_therapist',
                'manage_patient',
                'manage_global_at_patient',
                'view_audit_log',
                'view_dashboard',
                'manage_download_tracker',
                'generate_report',
                'view_number_of_clinic_therapist',
                'view_language',
                'view_exercise',
                'access_all',
            ],
            'library_admin' => [
                'get_library_category',
                'get_educational_material',
                'get_educational_material_file',
                'get_educational_material_category',
                'get_library_exercise',
                'get_exercise_file',
                'get_exercise_additional_field',
                'get_exercise_category',
                'get_library_questionnaire',
                'get_questionnaire_question',
                'get_question_file',
                'get_question_answer',
                'get_questionnaire_category',
            ],
            'translator' => [
                'manage_organization',
                'manage_guidance_page',
                'manage_translation',
                'manage_assistive_technology',
                'manage_profession',
                'manage_static_page',
                'manage_own_profile',
                'manage_clinic',
                'manage_download_tracker',
                'setup_educational_material',
                'setup_questionnaire',
                'setup_category',
                'setup_exercise',
                'view_country_therapist_limit',
                'view_default_limited_patient',
                'view_number_of_clinic_therapist',
                'view_category_tree',
            ],
        ];

        $this->line("Creating groups...");

        foreach ($groups as $groupName) {
            $created = KeycloakHelper::createGroup($groupName);
            if ($created) {
                $this->info(" - Group '{$groupName}': Created");
            } else {
                $this->warn(" - Group '{$groupName}': Already exists or failed");
            }
        }

        $this->line("Creating roles...");

        foreach ($roles as $role) {
            $created = KeycloakHelper::createRealmRole($role, "Role: $role");
            if ($created) {
                $this->info(" - Role '{$role}': Created");
            } else {
                $this->warn(" - Role '{$role}': Already exists or failed");
            }
        }

        $this->line("Assigning roles to groups...");

        foreach ($groupRoles as $group => $roles) {
            foreach ($roles as $role) {
                try {
                    $success = KeycloakHelper::assignRealmRoleToGroup($group, $role);
                    if ($success) {
                        $this->info(" - Group '{$group}' - Role '{$role}': Assigned");
                    } else {
                        $this->warn(" - Group '{$group}' - Role '{$role}': Failed");
                    }
                } catch (\Exception $e) {
                    $this->error("Error assigning role '{$role}' to group '{$group}': " . $e->getMessage());
                }
            }
        }

        $this->line("Role setup complete.");

        // Setup users 'hi_library'
        $email = env('KEYCLOAK_LIBRARY_USERNAME');
        $password = env('KEYCLOAK_LIBRARY_PASSWORD');

        $this->line("Setting up role user '$email'...");

        DB::beginTransaction();

        if (User::where('email', $email)->exists()) {
            $this->warn("Email already exists.");
            return;
        }

        $user = User::create([
            'email' => $email,
            'first_name' => 'DO_NOT_DELETE',
            'last_name' => 'DO_NOT_DELETE',
            'type' => '',
        ]);

        if (!$user) {
            $this->error("Failed to create user.");
            DB::rollBack();
            return;
        }

        try {
            $token = KeycloakHelper::getKeycloakAccessToken();
            if (!$token) {
                $this->error('Unable to get Keycloak token.');
                DB::rollBack();
                return;
            }

            $response = Http::withToken($token)
                ->withHeaders(['Content-Type' => 'application/json'])
                ->post(KeycloakHelper::getUserUrl(), [
                    'username'  => $user->email,
                    'firstName' => $user->first_name,
                    'lastName'  => $user->last_name,
                    'enabled'   => true,
                ]);

            if ($response->successful()) {
                $createdUserUrl = $response->header('Location');

                $requiredActionsRemoved = Http::withToken($token)
                    ->withHeaders(['Content-Type' => 'application/json'])
                    ->put(KeycloakHelper::getUserUrl() . '/' . basename($createdUserUrl), [
                        'requiredActions' => [],
                    ]);

                $passwordSet = KeycloakHelper::resetUserPassword($token, $createdUserUrl, $password, false);
                $groupAssigned = KeycloakHelper::assignUserToGroup($token, $createdUserUrl, 'library_admin');

                if (
                    $requiredActionsRemoved->successful()
                    && $passwordSet
                    && $groupAssigned
                ) {
                    DB::commit();
                    $this->info("User '$email' created and assigned to 'library_admin' group.");
                }
            }
        } catch (\Exception $e) {
            DB::rollBack();
            $this->error("Exception: " . $e->getMessage());
            return;
        }

        $this->line("Setup complete.");
    }
}
