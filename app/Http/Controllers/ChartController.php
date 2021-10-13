<?php

namespace App\Http\Controllers;

use App\Models\Clinic;
use App\Models\Country;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Http\Request;

class ChartController extends Controller
{

    /**
     * @OA\Get(
     *     path="/api/chart/admin-dashboard",
     *     tags={"Dashboard"},
     *     summary="Get admin dashboard data",
     *     operationId="getDashboardData",
     *     @OA\Response(
     *         response="200",
     *         description="successful operation"
     *     ),
     *     @OA\Response(response=400, description="Bad request"),
     *     @OA\Response(response=404, description="Resource Not Found"),
     *     @OA\Response(response=401, description="Authentication is required"),
     *     security={
     *         {
     *             "oauth2_security": {}
     *         }
     *     },
     * )
     *
     * @return array
     */
    public function getDataForAdminDashboard()
    {
        $globalAdminTotal = User::where('type', User::ADMIN_GROUP_GLOBAL_ADMIN)->where('enabled', '=', 1)->count();
        $countryAdminTotal = User::where('type', User::ADMIN_GROUP_COUNTRY_ADMIN)->where('enabled', '=', 1)->count();
        $clinicAdminTotal = User::where('type', User::ADMIN_GROUP_CLINIC_ADMIN)->where('enabled', '=', 1)->count();
        $clinicAdminsByCountry = DB::table('users')
            ->select(DB::raw('
                country_id,
                COUNT(*) AS total
            '))->where('type', User::ADMIN_GROUP_CLINIC_ADMIN)
            ->where('enabled', '=', 1)
            ->groupBy('country_id')
            ->get();
        $countryAdminsByCountry = DB::table('users')
            ->select(DB::raw('
                country_id,
                COUNT(*) AS total
            '))->where('type', User::ADMIN_GROUP_COUNTRY_ADMIN)
            ->where('enabled', '=', 1)
            ->groupBy('country_id')
            ->get();

        $patientData = [];
        $therapistData = [];

        $response = Http::get(env('PATIENT_SERVICE_URL') . '/chart/get-data-for-global-admin');

        if (!empty($response) && $response->successful()) {
            $patientData = $response->json();
        }

        $response = Http::get(env('THERAPIST_SERVICE_URL') . '/chart/get-data-for-global-admin');

        if (!empty($response) && $response->successful()) {
            $therapistData = $response->json();
        }

        $data = [
            'globalAdminTotal' => $globalAdminTotal,
            'countryAdminTotal' => $countryAdminTotal,
            'clinicAdminTotal' => $clinicAdminTotal,
            'clinicAdminsByCountry' => $clinicAdminsByCountry,
            'patientData' => $patientData,
            'therapistData' => $therapistData,
            'countryAdminByCountry' => $countryAdminsByCountry
        ];
        return ['success' => true, 'data' => $data];
    }

    /**
     * @OA\Get(
     *     path="/api/chart/country-admin-dashboard",
     *     tags={"Dashboard"},
     *     summary="Get country admin dashboard data",
     *     operationId="getCountryDashboardData",
     *     @OA\Parameter(
     *         name="country_id",
     *         in="query",
     *         description="Country id",
     *         required=true,
     *         @OA\Schema(
     *             type="integer"
     *         )
     *     ),
     *     @OA\Response(
     *         response="200",
     *         description="successful operation"
     *     ),
     *     @OA\Response(response=400, description="Bad request"),
     *     @OA\Response(response=404, description="Resource Not Found"),
     *     @OA\Response(response=401, description="Authentication is required"),
     *     security={
     *         {
     *             "oauth2_security": {}
     *         }
     *     },
     * )
     *
     * @param \Illuminate\Http\Request $request
     * @return array
     */
    public function getDataForCountryAdminDashboard(Request $request)
    {
        $country_id = $request->get('country_id');
        $clinicAdminTotal = DB::table('users')
            ->select(DB::raw('
                COUNT(*) AS total
            '))->where('type', User::ADMIN_GROUP_CLINIC_ADMIN)
            ->where('country_id', $country_id)
            ->where('enabled', '=', 1)
            ->get();
        $therapistLimit = Country::find($country_id);
        $patientData = [];
        $therapistData = [];

        $response = Http::get(env('PATIENT_SERVICE_URL') . '/chart/get-data-for-country-admin', [
            'country_id' => [$country_id]
        ]);

        if (!empty($response) && $response->successful()) {
            $patientData = $response->json();
        }

        $response = Http::get(env('THERAPIST_SERVICE_URL') . '/chart/get-data-for-country-admin', [
            'country_id' => [$country_id]
        ]);

        if (!empty($response) && $response->successful()) {
            $therapistData = $response->json();
        }
        $therapistData['therapistLimit'] = $therapistLimit->therapist_limit;
        $data = [
            'clinicAdminTotal' => $clinicAdminTotal,
            'patientData' => $patientData,
            'therapistData' => $therapistData,
        ];
        return ['success' => true, 'data' => $data];
    }

    /**
     * @OA\Get(
     *     path="/api/chart/clinic-admin-dashboard",
     *     tags={"Dashboard"},
     *     summary="Get clinic admin dashboard data",
     *     operationId="getClinicDashboardData",
     *     @OA\Parameter(
     *         name="clinic_id",
     *         in="query",
     *         description="Clinic id",
     *         required=true,
     *         @OA\Schema(
     *             type="integer"
     *         )
     *     ),
     *     @OA\Response(
     *         response="200",
     *         description="successful operation"
     *     ),
     *     @OA\Response(response=400, description="Bad request"),
     *     @OA\Response(response=404, description="Resource Not Found"),
     *     @OA\Response(response=401, description="Authentication is required"),
     *     security={
     *         {
     *             "oauth2_security": {}
     *         }
     *     },
     * )
     *
     * @param \Illuminate\Http\Request $request
     * @return array
     */
    public function getDataForClinicAdminDashboard(Request $request)
    {
        $clinicId = $request->get('clinic_id');
        $patientData = [];
        $therapistData = [];
        $therapistLimit = Clinic::find($clinicId);

        $response = Http::get(env('PATIENT_SERVICE_URL') . '/chart/get-data-for-clinic-admin', [
            'clinic_id' => [$clinicId]
        ]);

        if (!empty($response) && $response->successful()) {
            $patientData = $response->json();
        }

        $response = Http::get(env('THERAPIST_SERVICE_URL') . '/chart/get-data-for-clinic-admin', [
            'clinic_id' => [$clinicId]
        ]);

        if (!empty($response) && $response->successful()) {
            $therapistData = $response->json();
        }

        $therapistData['therapistLimit'] = $therapistLimit->therapist_limit;

        $data = [
            'patientData' => $patientData,
            'therapistData' => $therapistData,
        ];
        return ['success' => true, 'data' => $data];
    }
}
