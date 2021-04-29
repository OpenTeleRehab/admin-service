<?php

use App\Http\Controllers\ClinicController;
use App\Http\Controllers\CountryController;
use App\Http\Controllers\LanguageController;
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
    Route::apiResource('term-condition', TermAndConditionController::class);
    Route::post('term-condition/publish/{id}', [TermAndConditionController::class, 'publish']);
    Route::apiResource('privacy-policy', PrivacyPolicyController::class);
    Route::post('privacy-policy/publish/{id}', [PrivacyPolicyController::class, 'publish']);
    Route::apiResource('static-page', StaticPageController::class);
    Route::apiResource('partner-logo', PartnerLogoController::class);
    Route::post('admin/updateStatus/{user}', [AdminController::class, 'updateStatus']);

    Route::get('user/profile', [ProfileController::class, 'getUserProfile']);
    Route::put('user/update-password', [ProfileController::class, 'updatePassword']);
    Route::put('user/update-information', [ProfileController::class, 'updateUserProfile']);
    Route::put('user/update-last-access', [ProfileController::class, 'updateLastAccess']);

    Route::get('exercise/export/{type}', [ExerciseController::class, 'export']);

    Route::get('chart/admin-dashboard', [ChartController::class, 'getDataForAdminDashboard']);
    Route::get('chart/country-admin-dashboard', [ChartController::class, 'getDataForCountryAdminDashboard']);
    Route::get('chart/clinic-admin-dashboard', [ChartController::class, 'getDataForClinicAdminDashboard']);

    Route::post('import/exercises', [ImportController::class, 'importExercises']);
});

// Todo: apply for Admin, Therapist, Patient APPs
Route::apiResource('country', CountryController::class);
Route::get('country/list/defined-country', [CountryController::class, 'getDefinedCountries']);

Route::apiResource('clinic', ClinicController::class);
Route::get('clinic/therapist-limit/count/by-contry', [ClinicController::class, 'countTherapistLimitByCountry']);
Route::get('clinic/therapist/count/by-clinic', [ClinicController::class, 'countTherapistByClinic']);
Route::apiResource('language', LanguageController::class);
Route::get('language/by-id/{id}', [LanguageController::class, 'getById']);
Route::apiResource('file', FileController::class)->middleware('throttle:180:1');
Route::get('page/static', [StaticPageController::class, 'getStaticPage']);
Route::get('page/about', [StaticPageController::class, 'getAboutPage']);
Route::get('getDefaultLimitedPatient', [SettingController::class, 'getDefaultLimitedPatient']);
Route::apiResource('profession', ProfessionController::class);

Route::apiResource('exercise', ExerciseController::class);
Route::get('exercise/list/by-ids', [ExerciseController::class, 'getByIds']);
Route::post('exercise/mark-as-used/by-ids', [ExerciseController::class, 'markAsUsed']);
Route::post('exercise/updateFavorite/by-therapist/{exercise}', [ExerciseController::class, 'updateFavorite']);
Route::get('library/count/by-therapist', [ExerciseController::class, 'countTherapistLibrary']);

Route::apiResource('education-material', EducationMaterialController::class);
Route::get('education-material/list/by-ids', [EducationMaterialController::class, 'getByIds']);
Route::post('education-material/mark-as-used/by-ids', [EducationMaterialController::class, 'markAsUsed']);
Route::post('education-material/updateFavorite/by-therapist/{educationMaterial}', [EducationMaterialController::class, 'updateFavorite']);

Route::apiResource('questionnaire', QuestionnaireController::class);
Route::get('questionnaire/list/by-ids', [QuestionnaireController::class, 'getByIds']);
Route::post('questionnaire/mark-as-used/by-ids', [QuestionnaireController::class, 'markAsUsed']);
Route::post('questionnaire/updateFavorite/by-therapist/{questionnaire}', [QuestionnaireController::class, 'updateFavorite']);

Route::apiResource('category', CategoryController::class);
Route::get('category-tree', [CategoryController::class, 'getCategoryTreeData']);


Route::apiResource('system-limit', SystemLimitController::class);
Route::get('setting/library-limit', [SystemLimitController::class, 'getContentLimitForLibrary']);
Route::apiResource('settings', SettingController::class);

// Public access
Route::get('translation/i18n/{platform}', [TranslationController::class, 'getI18n']);
Route::get('user-term-condition', [TermAndConditionController::class, 'getUserTermAndCondition']);
Route::get('user-privacy-policy', [PrivacyPolicyController::class, 'getUserPrivacyPolicy']);
Route::get('partnerLogo', [PartnerLogoController::class, 'getPartnerLogo']);
