<?php

use App\Http\Controllers\AdminController;
use App\Http\Controllers\AssistiveTechnologyController;
use App\Http\Controllers\AuditLogController;
use App\Http\Controllers\CategoryController;
use App\Http\Controllers\ChartController;
use App\Http\Controllers\ClinicController;
use App\Http\Controllers\ColorSchemeController;
use App\Http\Controllers\CountryController;
use App\Http\Controllers\DownloadTrackerController;
use App\Http\Controllers\EducationMaterialController;
use App\Http\Controllers\ExerciseController;
use App\Http\Controllers\ExportController;
use App\Http\Controllers\FileController;
use App\Http\Controllers\ForwarderController;
use App\Http\Controllers\GlobalAssistiveTechnologyPatientController;
use App\Http\Controllers\GlobalPatientController;
use App\Http\Controllers\GuidancePageController;
use App\Http\Controllers\ImportController;
use App\Http\Controllers\InternationalClassificationDiseaseController;
use App\Http\Controllers\LanguageController;
use App\Http\Controllers\OrganizationController;
use App\Http\Controllers\PrivacyPolicyController;
use App\Http\Controllers\ProfessionController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\QuestionnaireController;
use App\Http\Controllers\SettingController;
use App\Http\Controllers\StaticPageController;
use App\Http\Controllers\SupersetController;
use App\Http\Controllers\SurveyController;
use App\Http\Controllers\SystemLimitController;
use App\Http\Controllers\TermAndConditionController;
use App\Http\Controllers\TranslationController;
use App\Http\Controllers\TranslatorController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider within a group which
| is assigned the "api" middleware group. Enjoy building your API!
|
*/

// Override public api resource
Route::get('public/term-condition/{id}', [TermAndConditionController::class, 'show']);
Route::get('public/privacy-policy/{id}', [PrivacyPolicyController::class, 'show']);

Route::group(['middleware' => ['auth:api', 'verify.data.access']], function () {
    // Admin
    Route::post('admin/updateStatus/{user}', [AdminController::class, 'updateStatus'])->middleware('role:manage_organization_admin,manage_country_admin,manage_clinic_admin');
    Route::post('admin/resend-email/{user}', [AdminController::class, 'resendEmailToUser'])->middleware('role:manage_organization_admin,manage_country_admin,manage_clinic_admin');
    Route::post('library/delete/by-therapist', [AdminController::class, 'deleteLibraryByTherapist'])->middleware('role:access_all');
    Route::apiResource('admin', AdminController::class)->middleware('role:manage_organization_admin,manage_country_admin,manage_clinic_admin');

    // Translator
    Route::post('translator/updateStatus/{user}', [TranslatorController::class, 'updateStatus'])->middleware('role:manage_translator');
    Route::post('translator/resend-email/{user}', [TranslatorController::class, 'resendEmailToUser'])->middleware('role:manage_translator');
    Route::apiResource('translator', TranslatorController::class)->middleware('role:manage_translator');

    // Assistive Technology
    Route::get('assistive-technologies', [AssistiveTechnologyController::class, 'index'])->middleware('role:manage_assistive_technology');
    Route::post('assistive-technologies', [AssistiveTechnologyController::class, 'store'])->middleware('role:manage_assistive_technology');
    Route::get('assistive-technologies/{assistiveTechnology}', [AssistiveTechnologyController::class, 'show'])->middleware('role:manage_assistive_technology');
    Route::put('assistive-technologies/{assistiveTechnology}', [AssistiveTechnologyController::class, 'update'])->middleware('role:manage_assistive_technology');
    Route::delete('assistive-technologies/{assistiveTechnology}', [AssistiveTechnologyController::class, 'destroy'])->middleware('role:manage_assistive_technology');
    Route::get('assistive-technologies/list/get-all', [AssistiveTechnologyController::class, 'getAllAssistiveTechnology'])->middleware('role:access_all');

    // Profession
    Route::get('profession', [ProfessionController::class, 'index'])->middleware('role:manage_profession,view_profession');
    Route::post('profession', [ProfessionController::class, 'store'])->middleware('role:manage_profession');
    Route::put('profession/{profession}', [ProfessionController::class, 'update'])->middleware('role:manage_profession');
    Route::delete('profession/{profession}', [ProfessionController::class, 'destroy'])->middleware('role:manage_profession');

    // Translation
    Route::apiResource('translation', TranslationController::class)->middleware('role:manage_translation');

    // Language
    Route::get('language/by-id/{id}', [LanguageController::class, 'getById'])->middleware('role:access_all');
    Route::put('language/language_auto_translate/{language}', [LanguageController::class, 'autoTranslate'])->middleware('role:manage_language');
    Route::get('language', [LanguageController::class, 'index'])->middleware('role:manage_language,view_language');
    Route::post('language', [LanguageController::class, 'store'])->middleware('role:manage_language');
    Route::put('language/{language}', [LanguageController::class, 'update'])->middleware('role:manage_language');
    Route::delete('language/{language}', [LanguageController::class, 'destroy'])->middleware('role:manage_language');

    // Organization
    Route::get('get-organization', [OrganizationController::class, 'getOrganization'])->middleware('role:access_all');
    Route::get('org/org-therapist-and-treatment-limit', [OrganizationController::class, 'getTherapistAndTreatmentLimit'])->middleware('role:manage_organization,view_organization');
    Route::get('organization', [OrganizationController::class, 'index'])->middleware('role:manage_organization,view_organization');
    Route::post('organization/{organization}', [OrganizationController::class, 'show'])->middleware('role:manage_organization');
    Route::post('organization', [OrganizationController::class, 'store'])->middleware('role:manage_organization');
    Route::put('organization/{organization}', [OrganizationController::class, 'update'])->middleware('role:manage_organization');
    Route::delete('organization/{organization}', [OrganizationController::class, 'destroy'])->middleware('role:manage_organization');

    // Diseases
    Route::get('disease/get-name/by-id', [InternationalClassificationDiseaseController::class, 'getDiseaseNameById'])->middleware('role:access_all');
    Route::apiResource('disease', InternationalClassificationDiseaseController::class)->middleware('role:manage_disease');

    // Country
    Route::get('get-country-by-iso-code', [CountryController::class, 'getCountryByIsoCode'])->middleware('role:access_all');
    Route::post('country', [CountryController::class, 'store'])->middleware('role:manage_country');
    Route::put('country/{country}', [CountryController::class, 'update'])->middleware('role:manage_country');
    Route::delete('country/{country}', [CountryController::class, 'destroy'])->middleware('role:manage_country');

    // Clinic
    Route::get('clinic/therapist-limit/count/by-country', [ClinicController::class, 'countTherapistLimitByCountry'])->middleware('role:view_country_therapist_limit');
    Route::get('clinic/therapist/count/by-clinic', [ClinicController::class, 'countTherapistByClinic'])->middleware('role:view_number_of_clinic_therapist');
    Route::get('clinic', [ClinicController::class, 'index'])->middleware('role:manage_clinic,view_clinic_list');
    Route::post('clinic', [ClinicController::class, 'store'])->middleware('role:manage_clinic');
    Route::put('clinic/{clinic}', [ClinicController::class, 'update'])->middleware('role:manage_clinic');
    Route::delete('clinic/{clinic}', [ClinicController::class, 'destroy'])->middleware('role:manage_clinic');

    // Library
    Route::get('library/count/by-therapist', [ExerciseController::class, 'countTherapistLibrary'])->middleware('role:access_all');

    // Exercise
    Route::get('exercise', [ExerciseController::class, 'index'])->middleware('role:setup_exercise,view_exercise_list');
    Route::post('exercise', [ExerciseController::class, 'store'])->middleware('role:setup_exercise');
    Route::get('exercise/{exercise}', [ExerciseController::class, 'show'])->middleware('role:setup_exercise');
    Route::put('exercise/{exercise}', [ExerciseController::class, 'update'])->middleware('role:setup_exercise');
    Route::delete('exercise/{exercise}', [ExerciseController::class, 'destroy'])->middleware('role:setup_exercise');
    Route::post('exercise/suggest', [ExerciseController::class, 'suggest'])->middleware('role:access_all');
    Route::get('exercise/list/by-ids', [ExerciseController::class, 'getByIds'])->middleware('role:access_all');
    Route::get('exercise/export/{type}', [ExerciseController::class, 'export'])->middleware('role:setup_exercise,export_exercise');
    Route::get('get-exercises', [ExerciseController::class, 'getExercises'])->middleware('role:access_all');
    Route::get('get-exercise-files', [ExerciseController::class, 'getExerciseFiles'])->middleware('role:get_exercise_file');
    Route::get('get-exercises-for-open-library', [ExerciseController::class, 'getExercisesForOpenLibrary'])->middleware('role:get_library_exercise');
    Route::get('get-exercise-categories-for-open-library', [ExerciseController::class, 'getExerciseCategoriesForOpenLibrary'])->middleware('role:get_exercise_category');
    Route::post('exercise/approve-translate/{exercise}', [ExerciseController::class, 'approveTranslation'])->middleware('role:setup_exercise');
    Route::post('exercise/updateFavorite/by-therapist/{exercise}', [ExerciseController::class, 'updateFavorite'])->middleware('role:access_all');

    // Education Material
    Route::get('education-material', [EducationMaterialController::class, 'index'])->middleware('role:setup_educational_material,view_educational_material_list');
    Route::post('education-material', [EducationMaterialController::class, 'store'])->middleware('role:setup_educational_material');
    Route::get('education-material/{educationMaterial}', [EducationMaterialController::class, 'show'])->middleware('role:setup_educational_material');
    Route::put('education-material/{educationMaterial}', [EducationMaterialController::class, 'update'])->middleware('role:setup_educational_material');
    Route::delete('education-material/{educationMaterial}', [EducationMaterialController::class, 'destroy'])->middleware('role:setup_educational_material');
    Route::post('education-material/suggest', [EducationMaterialController::class, 'suggest'])->middleware('role:access_all');
    Route::get('education-material/list/by-ids', [EducationMaterialController::class, 'getByIds'])->middleware('role:access_all');
    Route::get('get-education-materials', [EducationMaterialController::class, 'getEducationMaterials'])->middleware('role:access_all');
    Route::get('get-education-material-files', [EducationMaterialController::class, 'getEducationMaterialFiles'])->middleware('role:get_educational_material_file');
    Route::get('get-education-materials-for-open-library', [EducationMaterialController::class, 'getEducationMaterialsForOpenLibrary'])->middleware('role:get_educational_material');
    Route::get('get-education-material-categories-for-open-library', [EducationMaterialController::class, 'getEducationMaterialCategoriesForOpenLibrary'])->middleware('role:get_educational_material_category');
    Route::post('education-material/approve-translate/{educationMaterial}', [EducationMaterialController::class, 'approveTranslation'])->middleware('setup_educational_material');
    Route::post('education-material/updateFavorite/by-therapist/{educationMaterial}', [EducationMaterialController::class, 'updateFavorite'])->middleware('role:access_all');

    // Questionnaire
    Route::get('questionnaire', [QuestionnaireController::class, 'index'])->middleware('role:setup_questionnaire,view_questionnaire_list');
    Route::post('questionnaire', [QuestionnaireController::class, 'store'])->middleware('role:setup_questionnaire');
    Route::get('questionnaire/{questionnaire}', [QuestionnaireController::class, 'show'])->middleware('role:setup_questionnaire,view_questionnaire');
    Route::put('questionnaire/{questionnaire}', [QuestionnaireController::class, 'update'])->middleware('role:setup_questionnaire');
    Route::delete('questionnaire/{questionnaire}', [QuestionnaireController::class, 'destroy'])->middleware('role:setup_questionnaire');
    Route::post('questionnaire/suggest', [QuestionnaireController::class, 'suggest'])->middleware('role:access_all');
    Route::get('questionnaire/list/by-ids', [QuestionnaireController::class, 'getByIds'])->middleware('role:access_all');
    Route::get('get-questionnaires', [QuestionnaireController::class, 'getQuestionnaires'])->middleware('role:access_all');
    Route::get('get-questionnaire-questions', [QuestionnaireController::class, 'getQuestionnaireQuestions'])->middleware('role:get_questionnaire_question');
    Route::get('get-question-file', [QuestionnaireController::class, 'getQuestionFile'])->middleware('role:get_question_file');
    Route::get('get-question-answers', [QuestionnaireController::class, 'getQuestionAnswers'])->middleware('role:get_question_answer');
    Route::get('get-questionnaires-for-open-library', [QuestionnaireController::class, 'getQuestionnairesForOpenLibrary'])->middleware('role:get_library_questionnaire');
    Route::get('get-questionnaire-categories-for-open-library', [QuestionnaireController::class, 'getQuestionnaireCategoriesForOpenLibrary'])->middleware('role:get_questionnaire_category');
    Route::post('questionnaire/mark-as-used/by-ids', [QuestionnaireController::class, 'markAsUsed'])->middleware('role:access_all');
    Route::post('questionnaire/approve-translate/{questionnaire}', [QuestionnaireController::class, 'approveTranslation'])->middleware('role:setup_questionnaire');
    Route::post('questionnaire/updateFavorite/by-therapist/{questionnaire}', [QuestionnaireController::class, 'updateFavorite'])->middleware('role:access_all');
    Route::get('get-questionnaire-by-id', [QuestionnaireController::class, 'getById'])->middleware('role:access_all');
    Route::get('get-questionnaire-by-therapist', [QuestionnaireController::class, 'getByTherapist']); // deprecated
    Route::get('get-questionnaire-by-clinic-admin', [QuestionnaireController::class, 'getByClinicAdmin']); // deprecated
    Route::get('get-questionnaire-by-country-admin', [QuestionnaireController::class, 'getByCountryAdmin']); // deprecated

    // Additional Fields
    Route::get('get-exercise-additional-fields-for-open-library', [ExerciseController::class, 'getExerciseAdditionalFieldsForOpenLibrary'])->middleware('role:get_exercise_additional_field');

    // Guidance
    Route::post('guidance-page/update-order', [GuidancePageController::class, 'updateOrder'])->middleware('role:manage_guidance_page');
    Route::apiResource('guidance-page', GuidancePageController::class)->middleware('role:manage_guidance_page');

    // Settings
    Route::get('getDefaultLimitedPatient', [SettingController::class, 'getDefaultLimitedPatient'])->middleware('role:view_default_limited_patient');
    Route::get('system-limit/list/by-type', [SystemLimitController::class, 'getSystemLimitByType'])->middleware('role:access_all');
    Route::get('setting/library-limit', [SystemLimitController::class, 'getContentLimitForLibrary'])->middleware('role:access_all');
    Route::get('system-limit', [SystemLimitController::class, 'index'])->middleware('role:manage_system_limit,view_system_limit_list');
    Route::put('system-limit/{systemLimit}', [SystemLimitController::class, 'update'])->middleware('role:manage_system_limit');
    Route::get('settings', [SettingController::class, 'index'])->middleware('role:access_all');
    Route::get('static-page', [StaticPageController::class, 'index'])->middleware('role:manage_static_page');
    Route::post('static-page', [StaticPageController::class, 'store'])->middleware('role:manage_static_page');
    Route::get('static-page/{staticPage}', [StaticPageController::class, 'show'])->middleware('role:manage_static_page');
    Route::put('static-page/{staticPage}', [StaticPageController::class, 'update'])->middleware('role:manage_static_page');
    Route::get('term-condition', [TermAndConditionController::class, 'index'])->middleware('role:manage_term_condition,view_term_condition_list');
    Route::post('term-condition', [TermAndConditionController::class, 'store'])->middleware('role:manage_term_condition');
    Route::get('term-condition/{id}', [TermAndConditionController::class, 'show'])->middleware('role:manage_term_condition,view_term_condition');
    Route::put('term-condition/{id}', [TermAndConditionController::class, 'update'])->middleware('role:manage_term_condition');
    Route::get('privacy-policy', [PrivacyPolicyController::class, 'index'])->middleware('role:manage_privacy_policy,view_privacy_policy_list');
    Route::post('privacy-policy', [PrivacyPolicyController::class, 'store'])->middleware('role:manage_privacy_policy');
    Route::get('privacy-policy/{id}', [PrivacyPolicyController::class, 'show'])->middleware('role:manage_privacy_policy,view_privacy_policy');
    Route::put('privacy-policy/{id}', [PrivacyPolicyController::class, 'update'])->middleware('role:manage_privacy_policy');

    // Survey
    Route::get('survey', [SurveyController::class, 'index'])->middleware('role:manage_survey');
    Route::post('survey', [SurveyController::class, 'store'])->middleware('role:manage_survey');
    Route::get('survey/{survey}', [SurveyController::class, 'show'])->middleware('role:manage_survey');
    Route::put('survey/{survey}', [SurveyController::class, 'update'])->middleware('role:manage_survey');
    Route::post('survey/publish/{survey}', [SurveyController::class, 'publish'])->middleware('role:manage_survey');
    Route::post('survey/submit', [SurveyController::class, 'submit'])->middleware('role:submit_survey');
    Route::post('survey/skip', [SurveyController::class, 'skipSurvey'])->middleware('role:skip_survey');

    // Category
    Route::get('category-tree', [CategoryController::class, 'getCategoryTreeData'])->middleware('role:view_category_tree');
    Route::get('get-categories-for-open-library', [CategoryController::class, 'getCategoriesForOpenLibrary'])->middleware('role:get_library_category');
    Route::get('category', [CategoryController::class, 'index'])->middleware('role:setup_category,view_category_list');
    Route::post('category', [CategoryController::class, 'store'])->middleware('role:setup_category');
    Route::get('category/{category}', [CategoryController::class, 'show'])->middleware('role:setup_category');
    Route::put('category/{category}', [CategoryController::class, 'update'])->middleware('role:setup_category');
    Route::delete('category/{category}', [CategoryController::class, 'destroy'])->middleware('role:setup_category');

    Route::post('term-condition/publish/{id}', [TermAndConditionController::class, 'publish'])->middleware('role:manage_term_condition');
    Route::post('privacy-policy/publish/{id}', [PrivacyPolicyController::class, 'publish'])->middleware('role:manage_privacy_policy');

    Route::get('user/profile', [ProfileController::class, 'getUserProfile'])->middleware('role:manage_own_profile');
    Route::put('user/update-password', [ProfileController::class, 'updatePassword'])->middleware('role:manage_own_profile');
    Route::put('user/update-information', [ProfileController::class, 'updateUserProfile'])->middleware('role:manage_own_profile');
    Route::put('user/update-last-access', [ProfileController::class, 'updateLastAccess'])->middleware('role:manage_own_profile');

    // Dashboards
    Route::get('chart/admin-dashboard', [ChartController::class, 'getDataForAdminDashboard']); // deprecated
    Route::get('chart/country-admin-dashboard', [ChartController::class, 'getDataForCountryAdminDashboard']); // deprecated
    Route::get('chart/clinic-admin-dashboard', [ChartController::class, 'getDataForClinicAdminDashboard']); // deprecated

    // Imports
    Route::post('import/exercises', [ImportController::class, 'importExercises'])->middleware('role:import_exercise');
    Route::post('import/diseases', [ImportController::class, 'importDiseases'])->middleware('role:import_disease');

    // File Upload
    Route::post('file/upload', [FileController::class, 'uploadFile'])->middleware('role:file_upload');

    // Global Patients
    Route::get('global-patients', [GlobalPatientController::class, 'index'])->middleware('role:manage_global_patient');
    Route::delete('global-patients/{patiendId}', [GlobalPatientController::class, 'destroy'])->middleware('role:manage_global_patient');

    // Color Scheme
    Route::post('color-scheme', [ColorSchemeController::class, 'store'])->middleware('role:manage_color_scheme');

    // File
    Route::post('file', [FileController::class, 'uploadFile']); // not used

    // Admin Service
    Route::name('therapist.')->group(function () {
        Route::get('therapist/list/by-clinic-id', [ForwarderController::class, 'index'])->middleware('role:manage_therapist,view_clinic_therapist_list');
        Route::post('therapist/updateStatus/{id}', [ForwarderController::class, 'store'])->middleware('role:manage_therapist');
        Route::post('therapist/delete/by-id/{id}', [ForwarderController::class, 'store'])->middleware('role:manage_therapist,delete_therapist');
        Route::post('therapist/resend-email/{id}', [ForwarderController::class, 'store'])->middleware('role:manage_therapist');
        Route::get('therapist', [ForwarderController::class, 'index'])->middleware('role:manage_therapist,view_therapist_list');
        Route::post('therapist', [ForwarderController::class, 'store'])->middleware('role:manage_therapist');
        Route::put('therapist/{therapist}', [ForwarderController::class, 'update'])->middleware('role:manage_therapist');
        Route::get('transfer/number/by-therapist', [ForwarderController::class, 'index'])->middleware('role:manage_therapist,view_transfer_list_by_therapist');
    });

    // Patient Service
    Route::name('patient.')->group(function () {
        Route::get('patient', [ForwarderController::class, 'index'])->middleware('role:access_all');
        Route::get('patient/list/by-therapist-ids', [ForwarderController::class, 'index'])->middleware('role:manage_patient,view_therapist_patient_list');
        Route::get('patient/list/by-therapist-id', [ForwarderController::class, 'index'])->middleware('role:manage_patient');
        Route::get('patient/list/for-therapist-remove', [ForwarderController::class, 'index'])->middleware('role:manage_patient,view_remove_therapist_patient');
        Route::get('patient-treatment-plan', [ForwarderController::class, 'index'])->middleware('role:manage_patient,view_patient_treatment_plan');
        Route::get('patient-treatment-plan/get-treatment-plan-detail', [ForwarderController::class, 'index'])->middleware('role:manage_patient,view_patient_treatment_plan_detail');
        Route::post('patient/transfer-to-therapist/{id}', [ForwarderController::class, 'store'])->middleware('role:manage_patient');
    });

    // Global Assistive Technology Patients
    Route::apiResource('global-at-patients', GlobalAssistiveTechnologyPatientController::class)->middleware('role:manage_global_at_patient');

    // Audit logs
    Route::group(['prefix' => 'audit-logs'], function() {
        Route::get('/', [AuditLogController::class, 'index'])->middleware('role:view_audit_log');
        Route::post('/', [AuditLogController::class, 'store']); // Not used
    });

    // Superset
    Route::get('/superset-guest-token', [SupersetController::class, 'index'])->middleware('role:view_dashboard');

    // Download trackers
    Route::get('download-trackers', [DownloadTrackerController::class, 'index'])->middleware('role:manage_download_tracker');
    Route::put('download-trackers', [DownloadTrackerController::class, 'updateProgress'])->middleware('role:manage_download_tracker');
    Route::delete('download-trackers', [DownloadTrackerController::class, 'destroy'])->middleware('role:manage_download_tracker');

    Route::get('export', [ExportController::class, 'export'])->middleware('role:generate_report');
    Route::get('download-file', [FileController::class, 'download'])->middleware('role:access_all');
});
// Public Access
Route::get('color-scheme', [ColorSchemeController::class, 'index']);
Route::get('page/static', [StaticPageController::class, 'getStaticPage']);
Route::get('file/{id}', [FileController::class, 'show']);
Route::get('page/static-page-data', [StaticPageController::class, 'getStaticPageData']);
Route::get('page/term-condition', [TermAndConditionController::class, 'getTermAndConditionPage']);
Route::get('page/privacy', [PrivacyPolicyController::class, 'getPrivacyPage']);
Route::get('translation/i18n/{platform}', [TranslationController::class, 'getI18n']);
Route::get('public/user-term-condition', [TermAndConditionController::class, 'getUserTermAndCondition']);
Route::get('public/user-privacy-policy', [PrivacyPolicyController::class, 'getUserPrivacyPolicy']);
Route::get('update-organization-status', [OrganizationController::class, 'updateOrganizationStatus']);
Route::get('get-ongoing-organization', [OrganizationController::class, 'getOngoingOrganization']);
Route::get('org/org-therapist-and-max_sms', [OrganizationController::class, 'getTherapistAndMaxSms']);
Route::get('country', [CountryController::class, 'index']);
Route::get('country/list/by-clinic', [CountryController::class, 'getCountryByClinicId']);
Route::get('country/list/defined-country', [CountryController::class, 'getDefinedCountries']);
Route::get('public/term-condition', [TermAndConditionController::class, 'index']);
Route::get('public/privacy-policy', [PrivacyPolicyController::class, 'index']);
Route::get('language', [LanguageController::class, 'index']);
Route::get('assistive-technologies', [AssistiveTechnologyController::class, 'index']);
Route::get('clinic/get-by-id/{clinic}', [ClinicController::class, 'getById']);
Route::get('get-publish-survey', [SurveyController::class, 'getPublishSurveyByUserType']);
Route::post('audit-logs', [AuditLogController::class, 'store']);
