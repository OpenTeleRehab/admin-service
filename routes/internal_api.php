<?php

use App\Http\Controllers\Internal\UserController;
use App\Http\Controllers\Internal\RegionController;
use App\Http\Controllers\Internal\CountryController;
use App\Http\Controllers\Internal\ProvinceController;
use App\Http\Controllers\Internal\ClinicController;
use App\Http\Controllers\Internal\JobTrackerController;
use App\Http\Controllers\Internal\MfaSettingController;
use App\Http\Controllers\Internal\PhcServiceController;
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

Route::group(['middleware' => ['auth:api', 'role:access_all', 'verify.data.access']], function () {
    Route::get('user/{user}', [UserController::class, 'show']);
    Route::get('user/list/by-ids', [UserController::class, 'getByIds']);
    Route::get('user/list/by-regions', [UserController::class, 'getByRegions']);
    Route::get('user/list/by-name', [UserController::class, 'getByName']);
    Route::get('user/list/by-type', [UserController::class, 'getByType']);

    Route::get('region/by-ids', [RegionController::class, 'getByIds']);

    Route::get('country/by-ids', [CountryController::class, 'getByIds']);

    Route::get('province/by-ids', [ProvinceController::class, 'getByIds']);

    Route::get('clinic/by-ids', [ClinicController::class, 'getByIds']);

    Route::get('phc-service/by-ids', [PhcServiceController::class, 'getByIds']);

    // MFA settings
    Route::get('mfa-settings/get-for-therapist-service', [MfaSettingController::class, 'getMfaSettingsForTherapistService']);
    Route::delete('mfa-settings/{id}', [MfaSettingController::class, 'destroy']);

    // Job tracker
    Route::put('job-trackers/{id}', [JobTrackerController::class, 'update']);
});
