<?php

namespace App\Console\Commands;

use App\Helpers\KeycloakHelper;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;

class SetupKeycloakPermissions extends Command
{
    protected $signature = 'hi:keycloak-setup-permissions';
    protected $description = 'Create Keycloak roles and assign them to groups';

    public function handle()
    {
        $roles = [
            'translate_term_condition',
            'translate_exercise',
            'translate_educational_material',
            'translate_questionnaire',
            'translate_screening_questionnaire',
            'translate_privacy_policy',
            'translate_survey',
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
            'manage_survey',
            'manage_screening_questionnaire',
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
            'manage_region',
            'view_region_list',
            'manage_province',
            'view_province_list',
            'manage_phc_service',
            'view_phc_service_list',
            'mange_region',
            'manage_regional_admin',
            'manage_phc_service_admin',
            'manage_phc_worker',
            'view_phc_worker_list',
            'view_number_of_phc_service_phc_worker',
            'manage_health_condition',
            'manage_patient_referral',
            'manage_patient_referral_assignment',
            'manage_clinic',
            'translate_guidance_page',
            'translate_health_condition',
            'manage_mfa_policy',
            'view_questionnaire_list',
            'view_category_list',
            'delete_phc_worker',
            'view_exercise',
            'view_questionnaire',
            'view_educational_material',
            'view_country_limitation',
            'manage_system_limit',
            'view_remove_phc_worker_patient',
            'manage_faq_static_page',
            'manage_about_us_static_page',
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
            'manage_profession',
            'manage_guidance_page',
            'manage_api_client',
            'manage_clinic',
            'translate_guidance_page',
            'translate_health_condition',
        ];
        $groupRoles = [
            'super_admin' => [
                'setup_educational_material',
                'setup_questionnaire',
                'manage_translation',
                'setup_category',
                'manage_language',
                'manage_translator',
                'manage_system_limit',
                'manage_organization_admin',
                'manage_organization',
                'super_admin',
                'setup_exercise',
                'manage_assistive_technology',
                'view_category_tree',
                'manage_own_profile',
                'manage_screening_questionnaire',
                'import_exercise',
                'file_upload',
                'manage_health_condition',
                'manage_guidance_page',
                'manage_phc_worker_guidance',
                'manage_api_client',
                'manage_mfa_policy',
                'manage_faq_static_page',
            ],
            'organization_admin' => [
                'manage_system_limit',
                'manage_color_scheme',
                'view_clinic_list',
                'view_profession',
                'manage_about_us_static_page',
                'manage_country_admin',
                'manage_country',
                'manage_organization_admin',
                'view_exercise_list',
                'view_educational_material_list',
                'submit_survey',
                'skip_survey',
                'manage_own_profile',
                'manage_global_patient',
                'manage_survey',
                'view_clinic_therapist_list',
                'delete_therapist',
                'view_therapist_list',
                'view_transfer_list_by_therapist',
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
                'view_phc_worker_list',
                'delete_phc_worker',
                'view_questionnaire_list',
                'view_category_list',
                'manage_term_condition',
                'manage_privacy_policy',
                'manage_organization',
                'manage_mfa_policy',
                'view_exercise',
                'view_questionnaire',
                'view_educational_material',
                'view_region_list',
                'view_phc_service_list',
                'view_remove_phc_worker_patient',
                'file_upload',
            ],
            'country_admin' => [
                'view_organization',
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
                'view_exercise',
                'manage_survey',
                'manage_region',
                'manage_regional_admin',
                'manage_profession',
                'view_therapist_list',
                'delete_therapist',
                'view_phc_worker_list',
                'delete_phc_worker',
                'view_questionnaire',
                'view_educational_material',
                'manage_mfa_policy',
                'view_profession',
                'view_country_limitation',
                'view_therapist_patient_list',
                'view_transfer_list_by_therapist',
                'view_remove_therapist_patient',
                'view_clinic_therapist_list',
                'view_region_list',
                'view_phc_service_list',
                'view_remove_phc_worker_patient',
            ],
            'clinic_admin' => [
                'view_profession',
                'view_organization',
                'view_clinic_list',
                'submit_survey',
                'skip_survey',
                'manage_own_profile',
                'manage_global_patient',
                'manage_therapist',
                'manage_global_at_patient',
                'view_patient_treatment_plan',
                'view_patient_treatment_plan_detail',
                'view_audit_log',
                'view_dashboard',
                'manage_download_tracker',
                'generate_report',
                'view_number_of_clinic_therapist',
                'view_language',
                'view_exercise',
                'view_educational_material',
                'view_questionnaire',
                'manage_survey',
                'manage_patient_referral',
                'manage_patient_referral_assignment',
                'manage_mfa_policy',
                'view_therapist_patient_list',
                'view_remove_therapist_patient',
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
                'translate_translation',
                'translate_static_page',
                'translate_health_condition',
                'translate_assistive_technology',
                'translator',
                'translate_category',
                'translate_exercise',
                'translate_educational_material',
                'translate_questionnaire',
                'translate_screening_questionnaire',
                'translate_survey',
                'translate_privacy_policy',
                'translate_guidance_page',
                'translate_term_condition',
                'manage_own_profile',
                'view_category_tree',
            ],
            'regional_admin' => [
                'view_organization',
                'submit_survey',
                'skip_survey',
                'manage_own_profile',
                'manage_global_patient',
                'view_patient_treatment_plan',
                'view_patient_treatment_plan_detail',
                'manage_global_at_patient',
                'view_audit_log',
                'view_dashboard',
                'view_language',
                'view_region_list',
                'manage_province',
                'manage_clinic',
                'manage_clinic_admin',
                'manage_phc_service',
                'manage_phc_service_admin',
                'manage_survey',
                'view_therapist_list',
                'delete_therapist',
                'view_phc_worker_list',
                'delete_phc_worker',
                'manage_download_tracker',
                'generate_report',
                'view_exercise',
                'view_educational_material',
                'view_questionnaire',
                'manage_mfa_policy',
                'view_profession',
                'view_therapist_patient_list',
                'view_transfer_list_by_therapist',
                'view_remove_therapist_patient',
                'view_clinic_therapist_list',
                'view_remove_phc_worker_patient',
            ],
            'phc_service_admin' => [
                'view_profession',
                'view_organization',
                'submit_survey',
                'skip_survey',
                'manage_own_profile',
                'manage_global_patient',
                'manage_global_at_patient',
                'view_patient_treatment_plan',
                'view_patient_treatment_plan_detail',
                'view_audit_log',
                'view_dashboard',
                'view_language',
                'manage_phc_worker',
                'manage_survey',
                'view_number_of_phc_service_phc_worker',
                'manage_download_tracker',
                'generate_report',
                'view_exercise',
                'view_educational_material',
                'view_questionnaire',
                'manage_mfa_policy',
                'view_therapist_patient_list',
                'view_remove_phc_worker_patient',
            ],
        ];

        $this->line('Create roles...');

        foreach ($roles as $role) {
            $created = KeycloakHelper::createRealmRole($role, "Role: $role");
            if ($created) {
                $this->info(" - Role '{$role}': Created");
            } else {
                $this->warn(" - Role '{$role}': Already exists or failed");
            }
        }

        $this->line('Create new groups, and assign roles to all groups...');

        foreach ($groupRoles as $group => $roles) {
            $created = KeycloakHelper::createGroup($group);
            if ($created) {
                $this->info(" - Group '{$group}': Created");
            } else {
                $this->warn(" - Group '{$group}': Already exists or failed");
                // Remove all existing roles from group first
                try {
                    KeycloakHelper::removeAllRealmRolesFromGroup($group);
                    $this->info(" - Group '{$group}': Existing roles removed");
                } catch (\Throwable $e) {
                    $this->error(" - Group '{$group}': {$e->getMessage()}");
                    continue;
                }
            }

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
                    'username' => $user->email,
                    'firstName' => $user->first_name,
                    'lastName' => $user->last_name,
                    'enabled' => true,
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
