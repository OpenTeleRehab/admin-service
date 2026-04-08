<?php

namespace App\Console\Commands;

use App\Helpers\TreatmentPlanHelper;
use App\Models\Country;
use App\Models\Clinic;
use App\Models\Forwarder;
use App\Models\GlobalPatient;
use App\Models\GlobalTreatmentPlan;
use App\Models\PhcService;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;
use Spatie\Activitylog\Facades\Activity;

class SyncPatientData extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'hi:sync-patient-data {--all}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Sync patient data from global and vietnam hosts';

    /**
     * @return void
     * @throws \Illuminate\Http\Client\RequestException
     */
    public function handle()
    {
        $hosts = config('settings.hosting_country');
        // Disable activity logging for data sync
        Activity::disableLogging();
        // Sync patient and treatment plans from vn db or other country to new table.
        foreach ($hosts as $host) {
            $country = Country::where('iso_code', $host)->first();
            $access_token = Forwarder::getAccessToken(Forwarder::PATIENT_SERVICE, $host);

            if ($this->option('all')) {
                // Get all records.
                $patientData = json_decode(Http::withHeaders(['Authorization' => 'Bearer ' . $access_token, 'country' => $host])->get(env('PATIENT_SERVICE_URL') . '/patient/list/global', ['all' => true]));
                $treatmentPlanData = json_decode(Http::withHeaders(['Authorization' => 'Bearer ' . $access_token, 'country' => $host])->get(env('PATIENT_SERVICE_URL') . '/treatment-plan/list/global', ['all' => true]));
            } else {
                // Get only yesterday records.
                $patientData = json_decode(Http::withHeaders(['Authorization' => 'Bearer ' . $access_token, 'country' => $host])->get(env('PATIENT_SERVICE_URL') . '/patient/list/global'));
                $treatmentPlanData = json_decode(Http::withHeaders(['Authorization' => 'Bearer ' . $access_token, 'country' => $host])->get(env('PATIENT_SERVICE_URL') . '/treatment-plan/list/global'));
            }

            if ($patientData) {
                self::savePatientData($patientData);
            }

            if ($treatmentPlanData) {
                self::saveTreatmentPlanData($treatmentPlanData, $patientData);
            }
        }

        // Sync patient and treatment plans from global db to new table.
        $access_token = Forwarder::getAccessToken(Forwarder::PATIENT_SERVICE);

        if ($this->option('all')) {
            // Get all records.
            $patientGlobal = json_decode(Http::withToken($access_token)->get(env('PATIENT_SERVICE_URL') . '/patient/list/global', ['all' => true]));
            $treatmentPlanGlobal = json_decode(Http::withToken($access_token)->get(env('PATIENT_SERVICE_URL') . '/treatment-plan/list/global', ['all' => true]));
        } else {
            // Get only yesterday records.
            $patientGlobal = json_decode(Http::withToken($access_token)->get(env('PATIENT_SERVICE_URL') . '/patient/list/global'));
            $treatmentPlanGlobal = json_decode(Http::withToken($access_token)->get(env('PATIENT_SERVICE_URL') . '/treatment-plan/list/global'));
        }

        if ($patientGlobal) {
            self::savePatientData($patientGlobal);
        }

        if ($treatmentPlanGlobal) {
            self::saveTreatmentPlanData($treatmentPlanGlobal, $patientGlobal);
        }

        if ($this->option('all')) {
            // Force delete out of date global patient and treatment plan.
            GlobalPatient::whereDate('updated_at', '<', Carbon::today())->forceDelete();
            GlobalTreatmentPlan::whereDate('updated_at', '<', Carbon::today())->forceDelete();
        }

        // Re-enable activity logging after data sync
        Activity::enableLogging();

        $this->info('Data has been sync successfully');
    }

    /**
     * Save patient data to global tables.
     *
     * @param array $data
     * @return void
     */
    private static function savePatientData($data)
    {
        $refs = self::loadReferenceData(collect($data), ['country', 'clinic', 'phc_service']);
        foreach ($data as $patient) {
            if (!self::isValidData($patient, $refs, ['country','clinic','phc_service'])) {
                continue;
            }
            GlobalPatient::updateOrCreate(
                [
                    'patient_id' => $patient->id,
                    'country_id' => $patient->country_id,
                ],
                [
                    'gender' => $patient->gender,
                    'date_of_birth' => $patient->date_of_birth,
                    'identity' => $patient->identity,
                    'clinic_id' => $patient->clinic_id ?? null,
                    'phc_service_id' => $patient->phc_service_id ?? null,
                    'enabled' => $patient->enabled,
                    'location' => $patient->location,
                    'deleted_at' => $patient->deleted_at ? Carbon::parse($patient->deleted_at) : null,
                ]
            );
        }
    }

    /**
     * Save treatment plan data to global tables.
     *
     * @param array $data
     * @return void
     */
    private static function saveTreatmentPlanData($data, $patientData)
    {
        $refs = self::loadReferenceData(collect($patientData), ['country']);
        foreach ($data as $treatmentPlan) {
            $patient = current(array_filter($patientData, fn($patient) => $patient->id == $treatmentPlan->patient_id));
            $status = TreatmentPlanHelper::determineStatus($treatmentPlan->start_date, $treatmentPlan->end_date);
            if (!self::isValidData($patient, $refs, ['country'])) {
                continue;
            }
            GlobalTreatmentPlan::updateOrCreate(
                [
                    'treatment_id' => $treatmentPlan->id,
                    'patient_id' => $treatmentPlan->patient_id,
                    'country_id' => $patient->country_id
                ],
                [
                    'name' => $treatmentPlan->name,
                    'start_date' => date_create_from_format(config('settings.date_format'), $treatmentPlan->start_date)->format('Y-m-d'),
                    'end_date' => date_create_from_format(config('settings.date_format'), $treatmentPlan->end_date)->format('Y-m-d'),
                    'status' => $status,
                    'health_condition_id' => $treatmentPlan->health_condition_id,
                    'health_condition_group_id' => $treatmentPlan->health_condition_group_id,
                ]
            );
        }
    }

    /**
     * Load reference data.
     *
     * @param \Illuminate\Support\Collection $data
     * @param array $keys
     * @return array
     */
    private static function loadReferenceData($data, array $keys = [])
    {
        $refs = [];

        if (in_array('country', $keys)) {
            $countryIds = $data->pluck('country_id')->filter()->unique();
            $refs['countries'] = Country::whereIn('id', $countryIds)->get()->keyBy('id');
        }

        if (in_array('clinic', $keys)) {
            $clinicIds = $data->pluck('clinic_id')->filter()->unique();
            $refs['clinics'] = Clinic::whereIn('id', $clinicIds)->get()->keyBy('id');
        }

        if (in_array('phc_service', $keys)) {
            $phcServiceIds = $data->pluck('phc_service_id')->filter()->unique();
            $refs['phcServices'] = PhcService::whereIn('id', $phcServiceIds)->get()->keyBy('id');
        }

        return $refs;
    }

    /**
     * Validate data item against reference data.
     *
     * @param object $item
     * @param array $refs
     * @param array $keys
     * @return bool
     */
    private static function isValidData($item, array $refs, array $keys)
    {
        if (in_array('country', $keys) && (!isset($refs['countries']) || !$refs['countries']->has($item->country_id))) {
            return false;
        }

        if (in_array('clinic', $keys) && ($item->clinic_id && (!isset($refs['clinics']) || !$refs['clinics']->has($item->clinic_id)))) {
            return false;
        }

        if (in_array('phc_service', $keys) && ($item->phc_service_id && (!isset($refs['phcServices']) || !$refs['phcServices']->has($item->phc_service_id)))) {
            return false;
        }

        return true;
    }
}
