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
    const MIN_AGE = 0;
    const MAX_AGE = 80;
    const AGE_GAP = 10;

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

        $therapistData = [];

        $patientData = $this->getDataForGlobalAdmin();

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
        $country = Country::find($country_id);
        $clinicAdminTotal = DB::table('users')
            ->select(DB::raw('
                COUNT(*) AS total
            '))->where('type', User::ADMIN_GROUP_CLINIC_ADMIN)
            ->where('country_id', $country_id)
            ->where('enabled', '=', 1)
            ->get();

        $patientData = [];
        $therapistData = [];

        $response = Http::withHeaders([
            'country' => $country ? $country->iso_code : null
        ])->get(env('PATIENT_SERVICE_URL') . '/chart/get-data-for-country-admin', [
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
        $therapistData['therapistLimit'] = $country->therapist_limit;
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
        $clinic = Clinic::find($clinicId);
        $country = Country::find($clinic->country_id);
        $patientData = [];
        $therapistData = [];

        $response = Http::withHeaders([
            'country' => $country ? strtoupper($country->iso_code) : null
        ])->get(env('PATIENT_SERVICE_URL') . '/chart/get-data-for-clinic-admin', [
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

        $therapistData['therapistLimit'] = $clinic->therapist_limit;

        $data = [
            'patientData' => $patientData,
            'therapistData' => $therapistData,
        ];
        return ['success' => true, 'data' => $data];
    }

    /**
     * @return array
     */
    public function getDataForGlobalAdmin() {
        $patientsByGenderGroupedByCountry = DB::table('global_patients')
            ->select(DB::raw('
                country_id,
                SUM(CASE WHEN gender = "male" THEN 1 ELSE 0 END) AS male,
                SUM(CASE WHEN gender = "female" THEN 1 ELSE 0 END) AS female,
                SUM(CASE WHEN gender = "other" THEN 1 ELSE 0 END) AS other
            '))
            ->where('global_patients.enabled', '=', 1)
            ->where('global_patients.deleted_at', '=', null)
            ->groupBy('global_patients.country_id')
            ->get();
        $onGoingTreatmentsByGenderGroupedByCountry = DB::table('global_patients')
            ->select(DB::raw('
            global_patients.country_id,
            SUM(CASE WHEN gender = "male" AND start_date <= CURDATE() AND end_date >= CURDATE() THEN 1 ELSE 0 END) AS male,
            SUM(CASE WHEN gender = "female" AND start_date <= CURDATE() AND end_date >= CURDATE() THEN 1 ELSE 0 END) AS female,
            SUM(CASE WHEN gender = "other" AND start_date <= CURDATE() AND end_date >= CURDATE() THEN 1 ELSE 0 END) AS other
        '))
            ->join('global_treatment_plans',function($q) {
                $q->on('global_treatment_plans.patient_id', 'global_patients.patient_id');
                $q->on('global_treatment_plans.country_id', 'global_patients.country_id');
            })
            ->where('global_patients.enabled', '=', 1)
            ->where('global_patients.deleted_at', '=', null)
            ->groupBy('global_patients.country_id')
            ->get();


        $treatmentsByGender = DB::table('global_treatment_plans')
            ->select(DB::raw('
            global_patients.country_id,
            SUM(CASE WHEN gender = "male" THEN 1 ELSE 0 END) AS male,
            SUM(CASE WHEN gender = "female" THEN 1 ELSE 0 END) AS female,
            SUM(CASE WHEN gender = "other" THEN 1 ELSE 0 END) AS other
        '))
            ->leftJoin('global_patients',function($q) {
                $q->on('global_treatment_plans.patient_id', 'global_patients.patient_id');
                $q->on('global_treatment_plans.country_id', 'global_patients.country_id');
            })
            ->where('global_patients.enabled', '=', 1)
            ->where('global_patients.deleted_at', '=', null)
            ->groupBy('global_patients.country_id')
            ->get();

        $patientsByAgeGapGroupedByCountryColumns = '';
        $onGoingTreatmentsByAgeGapGroupedByCountryColumns = '';

        for ($i = self::MIN_AGE; $i <= self::MAX_AGE; ($i += self::AGE_GAP)) {
            if ($i === self::MIN_AGE) {
                $patientsByAgeGapGroupedByCountryColumns .= '
                SUM(CASE WHEN TIMESTAMPDIFF(YEAR, date_of_birth, CURDATE()) >= ' . $i .
                    ' AND TIMESTAMPDIFF(YEAR, date_of_birth, CURDATE()) < ' . ($i + 10) .
                    ' THEN 1 ELSE 0 END) AS `< ' . ($i + self::AGE_GAP) . '`,';

                $onGoingTreatmentsByAgeGapGroupedByCountryColumns .= '
                SUM(CASE WHEN start_date <= CURDATE() AND end_date >= CURDATE()
                AND TIMESTAMPDIFF(YEAR, date_of_birth, CURDATE()) >= ' . $i .
                    ' AND TIMESTAMPDIFF(YEAR, date_of_birth, CURDATE()) < ' . ($i + 10) .
                    ' THEN 1 ELSE 0 END) AS `< ' . ($i + self::AGE_GAP) . '`,';
            } elseif ($i < self::MAX_AGE) {
                $patientsByAgeGapGroupedByCountryColumns .= '
                SUM(CASE WHEN TIMESTAMPDIFF(YEAR, date_of_birth, CURDATE()) >= ' . $i .
                    ' AND TIMESTAMPDIFF(YEAR, date_of_birth, CURDATE()) < ' . ($i + 10) .
                    ' THEN 1 ELSE 0 END) AS `' . $i . ' - ' . ($i + self::AGE_GAP) . '`,';

                $onGoingTreatmentsByAgeGapGroupedByCountryColumns .= '
                SUM(CASE WHEN start_date <= CURDATE() AND end_date >= CURDATE()
                AND TIMESTAMPDIFF(YEAR, date_of_birth, CURDATE()) >= ' . $i .
                    ' AND TIMESTAMPDIFF(YEAR, date_of_birth, CURDATE()) < ' . ($i + 10) .
                    ' THEN 1 ELSE 0 END) AS `' . $i . ' - ' . ($i + self::AGE_GAP) . '`,';
            } else {
                $patientsByAgeGapGroupedByCountryColumns .= '
                SUM(CASE WHEN TIMESTAMPDIFF(YEAR, date_of_birth, CURDATE()) >= ' . $i .
                    ' THEN 1 ELSE 0 END) AS `>=' . $i . '`';

                $onGoingTreatmentsByAgeGapGroupedByCountryColumns .= '
                SUM(CASE WHEN start_date <= CURDATE() AND end_date >= CURDATE()
                AND TIMESTAMPDIFF(YEAR, date_of_birth, CURDATE()) >= ' . $i .
                    ' THEN 1 ELSE 0 END) AS `>=' . $i . '`';
            }
        }

        $patientsByAgeGapGroupedByCountry = DB::table('global_patients')
            ->select(DB::raw('
            country_id, ' . $patientsByAgeGapGroupedByCountryColumns))
            ->where('global_patients.enabled', '=', 1)
            ->where('global_patients.deleted_at', '=', null)
            ->groupBy('global_patients.country_id')
            ->get();

        $onGoingTreatmentsByAgeGapGroupedByCountry = DB::table('global_patients')
            ->select(DB::raw('
            global_patients.country_id, ' . $onGoingTreatmentsByAgeGapGroupedByCountryColumns))
            ->where('global_patients.enabled', '=', 1)
            ->where('global_patients.deleted_at', '=', null)
            ->groupBy('global_patients.country_id')
            ->join('global_treatment_plans',function($q) {
                $q->on('global_treatment_plans.patient_id', 'global_patients.patient_id');
                $q->on('global_treatment_plans.country_id', 'global_patients.country_id');
            })
            ->get();

        $treatmentsByAgeGapGroupedByCountry = DB::table('global_treatment_plans')
            ->select(DB::raw('
            global_patients.country_id, ' . $patientsByAgeGapGroupedByCountryColumns))
            ->where('global_patients.enabled', '=', 1)
            ->where('global_patients.deleted_at', '=', null)
            ->groupBy('global_patients.country_id')
            ->join('global_patients', function($q) {
                $q->on('global_treatment_plans.patient_id', 'global_patients.patient_id');
                $q->on('global_treatment_plans.country_id', 'global_patients.country_id');
            })
            ->get();

        return [
            'patientsByGenderGroupedByCountry' => $patientsByGenderGroupedByCountry,
            'onGoingTreatmentsByGenderGroupedByCountry' => $onGoingTreatmentsByGenderGroupedByCountry,
            'treatmentsByGender' => $treatmentsByGender,
            'patientsByAgeGapGroupedByCountry' => $patientsByAgeGapGroupedByCountry,
            'onGoingTreatmentsByAgeGapGroupedByCountry' => $onGoingTreatmentsByAgeGapGroupedByCountry,
            'treatmentsByAgeGapGroupedByCountry' => $treatmentsByAgeGapGroupedByCountry,
        ];
    }
}
