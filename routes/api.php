<?php

use App\Http\Controllers\GlobalAssistiveTechnologyPatientController;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\ClinicController;
use App\Http\Controllers\CountryController;
use App\Http\Controllers\GlobalPatientController;
use App\Http\Controllers\GuidancePageController;
use App\Http\Controllers\InternationalClassificationDiseaseController;
use App\Http\Controllers\LanguageController;
use App\Http\Controllers\OrganizationController;
use App\Http\Controllers\ColorSchemeController;
use App\Http\Controllers\PrivacyPolicyController;
use App\Http\Controllers\ProfessionController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\SettingController;
use App\Http\Controllers\StaticPageController;
use App\Http\Controllers\SystemLimitController;
use App\Http\Controllers\TranslationController;
use App\Http\Controllers\AdminController;
use App\Http\Controllers\ExerciseController;
use App\Http\Controllers\FileController;
use App\Http\Controllers\TermAndConditionController;
use App\Http\Controllers\EducationMaterialController;
use App\Http\Controllers\QuestionnaireController;
use App\Http\Controllers\CategoryController;
use App\Http\Controllers\ChartController;
use App\Http\Controllers\ImportController;
use App\Http\Controllers\TranslatorController;
use App\Http\Controllers\ForwarderController;
use App\Http\Controllers\AssistiveTechnologyController;
use App\Http\Controllers\AuditLogController;

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
Route::get('term-condition/{id}', [TermAndConditionController::class, 'show']);
Route::get('privacy-policy/{id}', [PrivacyPolicyController::class, 'show']);

Route::group(['middleware' => 'auth:api'], function () {
    // Admin
    Route::post('admin/updateStatus/{user}', [AdminController::class, 'updateStatus']);
    Route::post('admin/resend-email/{user}', [AdminController::class, 'resendEmailToUser']);
    Route::post('library/delete/by-therapist', [AdminController::class, 'deleteLibraryByTherapist']);
    Route::apiResource('admin', AdminController::class);

    // Translator
    Route::post('translator/updateStatus/{user}', [TranslatorController::class, 'updateStatus']);
    Route::post('translator/resend-email/{user}', [TranslatorController::class, 'resendEmailToUser']);
    Route::apiResource('translator', TranslatorController::class);

    // Assistive Technology
    Route::apiResource('assistive-technologies', AssistiveTechnologyController::class);
    Route::get('assistive-technologies/list/get-all', [AssistiveTechnologyController::class, 'getAllAssistiveTechnology']);

    // Profession
    Route::apiResource('profession', ProfessionController::class);

    // Translation
    Route::apiResource('translation', TranslationController::class);

    // Language
    Route::get('language/by-id/{id}', [LanguageController::class, 'getById']);
    Route::put('language/language_auto_translate/{language}', [LanguageController::class, 'autoTranslate']);
    Route::apiResource('language', LanguageController::class);

    // Organization
    Route::get('get-organization', [OrganizationController::class, 'getOrganization']);
    Route::get('org/org-therapist-and-treatment-limit', [OrganizationController::class, 'getTherapistAndTreatmentLimit']);
    Route::apiResource('organization', OrganizationController::class);

    // Diseases
    Route::get('disease/get-name/by-id', [InternationalClassificationDiseaseController::class, 'getDiseaseNameById']);
    Route::apiResource('disease', InternationalClassificationDiseaseController::class);

    // Country
    Route::apiResource('country', CountryController::class);

    // Clinic
    Route::get('clinic/therapist-limit/count/by-country', [ClinicController::class, 'countTherapistLimitByCountry']);
    Route::get('clinic/therapist/count/by-clinic', [ClinicController::class, 'countTherapistByClinic']);
    Route::apiResource('clinic', ClinicController::class);

    // Library
    Route::get('library/count/by-therapist', [ExerciseController::class, 'countTherapistLibrary']);

    // Exercise
    Route::apiResource('exercise', ExerciseController::class);
    Route::post('exercise/suggest', [ExerciseController::class, 'suggest']);
    Route::get('exercise/list/by-ids', [ExerciseController::class, 'getByIds']);
    Route::get('exercise/export/{type}', [ExerciseController::class, 'export']);
    Route::get('get-exercises', [ExerciseController::class, 'getExercises']);
    Route::get('get-exercise-files', [ExerciseController::class, 'getExerciseFiles']);
    Route::get('get-exercises-for-open-library', [ExerciseController::class, 'getExercisesForOpenLibrary']);
    Route::get('get-exercise-categories-for-open-library', [ExerciseController::class, 'getExerciseCategoriesForOpenLibrary']);
    Route::post('exercise/approve-translate/{exercise}', [ExerciseController::class, 'approveTranslation']);
    Route::post('exercise/updateFavorite/by-therapist/{exercise}', [ExerciseController::class, 'updateFavorite']);

    // Education Material
    Route::apiResource('education-material', EducationMaterialController::class);
    Route::post('education-material/suggest', [EducationMaterialController::class, 'suggest']);
    Route::get('education-material/list/by-ids', [EducationMaterialController::class, 'getByIds']);
    Route::get('get-education-materials', [EducationMaterialController::class, 'getEducationMaterials']);
    Route::get('get-education-material-files', [EducationMaterialController::class, 'getEducationMaterialFiles']);
    Route::get('get-education-materials-for-open-library', [EducationMaterialController::class, 'getEducationMaterialsForOpenLibrary']);
    Route::get('get-education-material-categories-for-open-library', [EducationMaterialController::class, 'getEducationMaterialCategoriesForOpenLibrary']);
    Route::post('education-material/approve-translate/{educationMaterial}', [EducationMaterialController::class, 'approveTranslation']);
    Route::post('education-material/updateFavorite/by-therapist/{educationMaterial}', [EducationMaterialController::class, 'updateFavorite']);

    // Questionnaire
    Route::apiResource('questionnaire', QuestionnaireController::class);
    Route::post('questionnaire/suggest', [QuestionnaireController::class, 'suggest']);
    Route::get('questionnaire/list/by-ids', [QuestionnaireController::class, 'getByIds']);
    Route::get('get-questionnaires', [QuestionnaireController::class, 'getQuestionnaires']);
    Route::get('get-questionnaire-questions', [QuestionnaireController::class, 'getQuestionnaireQuestions']);
    Route::get('get-question-file', [QuestionnaireController::class, 'getQuestionFile']);
    Route::get('get-question-answers', [QuestionnaireController::class, 'getQuestionAnswers']);
    Route::get('get-questionnaires-for-open-library', [QuestionnaireController::class, 'getQuestionnairesForOpenLibrary']);
    Route::get('get-questionnaire-categories-for-open-library', [QuestionnaireController::class, 'getQuestionnaireCategoriesForOpenLibrary']);
    Route::post('questionnaire/mark-as-used/by-ids', [QuestionnaireController::class, 'markAsUsed']);
    Route::post('questionnaire/approve-translate/{questionnaire}', [QuestionnaireController::class, 'approveTranslation']);
    Route::post('questionnaire/updateFavorite/by-therapist/{questionnaire}', [QuestionnaireController::class, 'updateFavorite']);

    // Additional Fields
    Route::get('get-exercise-additional-fields-for-open-library', [ExerciseController::class, 'getExerciseAdditionalFieldsForOpenLibrary']);

    // Guidance
    Route::post('guidance-page/update-order', [GuidancePageController::class, 'updateOrder']);
    Route::apiResource('guidance-page', GuidancePageController::class);

    // Settings
    Route::get('getDefaultLimitedPatient', [SettingController::class, 'getDefaultLimitedPatient']);
    Route::get('system-limit/list/by-type', [SystemLimitController::class, 'getSystemLimitByType']);
    Route::get('setting/library-limit', [SystemLimitController::class, 'getContentLimitForLibrary']);
    Route::apiResource('system-limit', SystemLimitController::class);
    Route::apiResource('settings', SettingController::class);
    Route::apiResource('static-page', StaticPageController::class);
    Route::apiResource('term-condition', TermAndConditionController::class);
    Route::apiResource('privacy-policy', PrivacyPolicyController::class);

    // Category
    Route::get('category-tree', [CategoryController::class, 'getCategoryTreeData']);
    Route::get('get-categories-for-open-library', [CategoryController::class, 'getCategoriesForOpenLibrary']);
    Route::apiResource('category', CategoryController::class);

    Route::post('term-condition/publish/{id}', [TermAndConditionController::class, 'publish']);
    Route::post('privacy-policy/publish/{id}', [PrivacyPolicyController::class, 'publish']);

    Route::get('user/profile', [ProfileController::class, 'getUserProfile']);
    Route::put('user/update-password', [ProfileController::class, 'updatePassword']);
    Route::put('user/update-information', [ProfileController::class, 'updateUserProfile']);
    Route::put('user/update-last-access', [ProfileController::class, 'updateLastAccess']);

    // Dashboards
    Route::get('chart/admin-dashboard', [ChartController::class, 'getDataForAdminDashboard']);
    Route::get('chart/country-admin-dashboard', [ChartController::class, 'getDataForCountryAdminDashboard']);
    Route::get('chart/clinic-admin-dashboard', [ChartController::class, 'getDataForClinicAdminDashboard']);

    // Imports
    Route::post('import/exercises', [ImportController::class, 'importExercises']); // Need check
    Route::post('import/diseases', [ImportController::class, 'importDiseases']); // Need check

    // File Upload
    Route::post('file/upload', [FileController::class, 'uploadFile']);

    // Global Patients
    Route::apiResource('global-patients', GlobalPatientController::class);

    // Color Scheme
    Route::post('color-scheme', [ColorSchemeController::class, 'store']);

    // File
    Route::post('file', [FileController::class, 'uploadFile']);

    // Admin Service
    Route::name('therapist.')->group(function () {
        Route::get('therapist/list/by-clinic-id', [ForwarderController::class, 'index']);
        Route::post('therapist/updateStatus/{id}', [ForwarderController::class, 'store']);
        Route::post('therapist/delete/by-id/{id}', [ForwarderController::class, 'store']);
        Route::post('therapist/resend-email/{id}', [ForwarderController::class, 'store']);
        Route::apiResource('therapist', ForwarderController::class);
        Route::get('transfer/number/by-therapist', [ForwarderController::class, 'index']);
    });

    // Patient Service
    Route::name('patient.')->group(function () {
        Route::get('patient', [ForwarderController::class, 'index']);
        Route::get('patient/list/by-therapist-ids', [ForwarderController::class, 'index']);
        Route::get('patient/list/by-therapist-id', [ForwarderController::class, 'index']);
        Route::get('patient/list/for-therapist-remove', [ForwarderController::class, 'index']);
        Route::get('patient-treatment-plan', [ForwarderController::class, 'index']);
        Route::get('patient-treatment-plan/get-treatment-plan-detail', [ForwarderController::class, 'index']);
        Route::post('patient/transfer-to-therapist/{id}', [ForwarderController::class, 'store']);
    });

    // Global Assistive Technology Patients
    Route::apiResource('global-at-patients', GlobalAssistiveTechnologyPatientController::class);

    // Audit logs
    Route::get('audit-logs', [AuditLogController::class, 'index']);
});

// Public Access
Route::get('color-scheme', [ColorSchemeController::class, 'index']);
Route::get('page/static', [StaticPageController::class, 'getStaticPage']);
Route::get('file/{id}', [FileController::class, 'show']);
Route::get('page/static-page-data', [StaticPageController::class, 'getStaticPageData']);
Route::get('page/term-condition', [TermAndConditionController::class, 'getTermAndConditionPage']);
Route::get('page/privacy', [PrivacyPolicyController::class, 'getPrivacyPage']);
Route::get('translation/i18n/{platform}', [TranslationController::class, 'getI18n']);
Route::get('user-term-condition', [TermAndConditionController::class, 'getUserTermAndCondition']);
Route::get('user-privacy-policy', [PrivacyPolicyController::class, 'getUserPrivacyPolicy']);
Route::get('update-organization-status', [OrganizationController::class, 'updateOrganizationStatus']);
Route::get('get-ongoing-organization', [OrganizationController::class, 'getOngoingOrganization']);
Route::get('org/org-therapist-and-max_sms', [OrganizationController::class, 'getTherapistAndMaxSms']);
Route::get('country', [CountryController::class, 'index']);
Route::get('country/list/by-clinic', [CountryController::class, 'getCountryByClinicId']);
Route::get('country/list/defined-country', [CountryController::class, 'getDefinedCountries']);
Route::get('term-condition', [TermAndConditionController::class, 'index']);
Route::get('privacy-policy', [PrivacyPolicyController::class, 'index']);
Route::get('language', [LanguageController::class, 'index']);
Route::get('assistive-technologies', [AssistiveTechnologyController::class, 'index']);
Route::get('clinic/get-by-id/{clinic}', [ClinicController::class, 'getById']);
