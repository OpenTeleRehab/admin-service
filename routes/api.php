<?php

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
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\AdminController;
use App\Http\Controllers\ExerciseController;
use \App\Http\Controllers\FileController;
use \App\Http\Controllers\TermAndConditionController;
use \App\Http\Controllers\EducationMaterialController;
use \App\Http\Controllers\QuestionnaireController;
use \App\Http\Controllers\CategoryController;
use \App\Http\Controllers\ChartController;
use \App\Http\Controllers\ImportController;
use \App\Http\Controllers\PartnerLogoController;
use \App\Http\Controllers\TranslatorController;

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

Route::group(['middleware' => 'auth:api'], function () {
    Route::apiResource('admin', AdminController::class);
    Route::apiResource('translation', TranslationController::class);
    Route::apiResource('translator', TranslatorController::class);
    Route::post('translator/updateStatus/{user}', [TranslatorController::class, 'updateStatus']);
    Route::post('translator/resend-email/{user}', [TranslatorController::class, 'resendEmailToUser']);
    Route::post('term-condition/publish/{id}', [TermAndConditionController::class, 'publish']);
    Route::post('privacy-policy/publish/{id}', [PrivacyPolicyController::class, 'publish']);
    Route::apiResource('static-page', StaticPageController::class);
    Route::apiResource('partner-logo', PartnerLogoController::class);
    Route::post('admin/updateStatus/{user}', [AdminController::class, 'updateStatus']);
    Route::post('admin/resend-email/{user}', [AdminController::class, 'resendEmailToUser']);
    Route::apiResource('organization', OrganizationController::class);

    Route::get('user/profile', [ProfileController::class, 'getUserProfile']);
    Route::put('user/update-password', [ProfileController::class, 'updatePassword']);
    Route::put('user/update-information', [ProfileController::class, 'updateUserProfile']);
    Route::put('user/update-last-access', [ProfileController::class, 'updateLastAccess']);

    Route::get('exercise/export/{type}', [ExerciseController::class, 'export']);

    Route::get('chart/admin-dashboard', [ChartController::class, 'getDataForAdminDashboard']);
    Route::get('chart/country-admin-dashboard', [ChartController::class, 'getDataForCountryAdminDashboard']);
    Route::get('chart/clinic-admin-dashboard', [ChartController::class, 'getDataForClinicAdminDashboard']);

    Route::post('import/exercises', [ImportController::class, 'importExercises']);
    Route::post('import/diseases', [ImportController::class, 'importDiseases']);
    Route::apiResource('global-patients', GlobalPatientController::class);
});

// Todo: apply for Admin, Therapist, Patient APPs
Route::apiResource('country', CountryController::class);
Route::get('country/list/defined-country', [CountryController::class, 'getDefinedCountries']);
Route::get('country/list/by-clinic', [CountryController::class, 'getCountryByClinicId']);

Route::apiResource('clinic', ClinicController::class);
Route::get('clinic/therapist-limit/count/by-contry', [ClinicController::class, 'countTherapistLimitByCountry']);
Route::get('clinic/therapist/count/by-clinic', [ClinicController::class, 'countTherapistByClinic']);
Route::apiResource('language', LanguageController::class);
Route::get('language/by-id/{id}', [LanguageController::class, 'getById']);
Route::put('language/language_auto_translate/{language}', [LanguageController::class, 'autoTranslate']);
Route::post('file/upload', [FileController::class, 'uploadFile']);
Route::apiResource('file', FileController::class)->middleware('throttle:180:1');
Route::get('page/static', [StaticPageController::class, 'getStaticPage']);
Route::get('page/static-page-data', [StaticPageController::class, 'getStaticPageData']);
Route::apiResource('guidance-page', GuidancePageController::class);
Route::post('guidance-page/update-order', [GuidancePageController::class, 'updateOrder']);
Route::get('page/term-condition', [TermAndConditionController::class, 'getTermAndConditionPage']);
Route::get('page/privacy', [PrivacyPolicyController::class, 'getPrivacyPage']);
Route::get('getDefaultLimitedPatient', [SettingController::class, 'getDefaultLimitedPatient']);
Route::apiResource('profession', ProfessionController::class);
Route::apiResource('disease', InternationalClassificationDiseaseController::class);

Route::apiResource('exercise', ExerciseController::class)->middleware('throttle:240:1');
Route::post('exercise/suggest', [ExerciseController::class, 'suggest']);
Route::post('exercise/approve-translate/{exercise}', [ExerciseController::class, 'approveTranslation']);
Route::get('exercise/list/by-ids', [ExerciseController::class, 'getByIds']);
Route::post('exercise/updateFavorite/by-therapist/{exercise}', [ExerciseController::class, 'updateFavorite']);
Route::get('library/count/by-therapist', [ExerciseController::class, 'countTherapistLibrary']);
Route::post('library/delete/by-therapist', [ExerciseController::class, 'deleteLibraryByTherapist']);
Route::get('get-exercises', [ExerciseController::class, 'getExercises']);
Route::get('get-exercise-files', [ExerciseController::class, 'getExerciseFiles']);
Route::get('get-exercises-for-open-library', [ExerciseController::class, 'getExercisesForOpenLibrary']);
Route::get('get-exercise-categories-for-open-library', [ExerciseController::class, 'getExerciseCategoriesForOpenLibrary']);

Route::apiResource('education-material', EducationMaterialController::class)->middleware('throttle:240:1');
Route::post('education-material/suggest', [EducationMaterialController::class, 'suggest']);
Route::post('education-material/approve-translate/{educationMaterial}', [EducationMaterialController::class, 'approveTranslation']);
Route::get('education-material/list/by-ids', [EducationMaterialController::class, 'getByIds']);
Route::post('education-material/updateFavorite/by-therapist/{educationMaterial}', [EducationMaterialController::class, 'updateFavorite']);
Route::get('get-education-materials', [EducationMaterialController::class, 'getEducationMaterials']);
Route::get('get-education-material-files', [EducationMaterialController::class, 'getEducationMaterialFiles']);
Route::get('get-education-materials-for-open-library', [EducationMaterialController::class, 'getEducationMaterialsForOpenLibrary']);
Route::get('get-education-material-categories-for-open-library', [EducationMaterialController::class, 'getEducationMaterialCategoriesForOpenLibrary']);

Route::apiResource('questionnaire', QuestionnaireController::class)->middleware('throttle:240:1');
Route::post('questionnaire/suggest', [QuestionnaireController::class, 'suggest']);
Route::post('questionnaire/approve-translate/{questionnaire}', [QuestionnaireController::class, 'approveTranslation']);
Route::get('questionnaire/list/by-ids', [QuestionnaireController::class, 'getByIds']);
Route::post('questionnaire/mark-as-used/by-ids', [QuestionnaireController::class, 'markAsUsed']);
Route::post('questionnaire/updateFavorite/by-therapist/{questionnaire}', [QuestionnaireController::class, 'updateFavorite']);
Route::get('get-questionnaires', [QuestionnaireController::class, 'getQuestionnaires']);
Route::get('get-questionnaire-questions', [QuestionnaireController::class, 'getQuestionnaireQuestions']);
Route::get('get-question-file', [QuestionnaireController::class, 'getQuestionFile']);
Route::get('get-question-answers', [QuestionnaireController::class, 'getQuestionAnswers']);
Route::get('get-questionnaires-for-open-library', [QuestionnaireController::class, 'getQuestionnairesForOpenLibrary']);
Route::get('get-questionnaire-categories-for-open-library', [QuestionnaireController::class, 'getQuestionnaireCategoriesForOpenLibrary']);

Route::apiResource('category', CategoryController::class);
Route::get('category-tree', [CategoryController::class, 'getCategoryTreeData']);
Route::get('get-categories-for-open-library', [CategoryController::class, 'getCategoriesForOpenLibrary']);


Route::apiResource('system-limit', SystemLimitController::class);
Route::get('system-limit/list/by-type', [SystemLimitController::class, 'getSystemLimitByType']);
Route::get('setting/library-limit', [SystemLimitController::class, 'getContentLimitForLibrary']);
Route::apiResource('settings', SettingController::class);
Route::get('org/org-therapist-and-treatment-limit', [OrganizationController::class, 'getTherapistAndTreatmentLimit']);
Route::apiResource('color-scheme', ColorSchemeController::class);

// Public access
Route::apiResource('term-condition', TermAndConditionController::class);
Route::apiResource('privacy-policy', PrivacyPolicyController::class);

Route::get('translation/i18n/{platform}', [TranslationController::class, 'getI18n']);
Route::get('user-term-condition', [TermAndConditionController::class, 'getUserTermAndCondition']);
Route::get('user-privacy-policy', [PrivacyPolicyController::class, 'getUserPrivacyPolicy']);
Route::get('partnerLogo', [PartnerLogoController::class, 'getPartnerLogo']);
Route::get('disease/get-name/by-id', [InternationalClassificationDiseaseController::class, 'getDiseaseNameById']);
Route::get('get-organization', [OrganizationController::class, 'getOrganization']);
Route::get('get-ongoing-organization', [OrganizationController::class, 'getOngoingOrganization']);
Route::get('update-organization-status', [OrganizationController::class, 'updateOrganizationStatus']);
