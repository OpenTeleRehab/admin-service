<?php

namespace App\Console\Commands;

use App\Models\Country;
use App\Models\Forwarder;
use App\Models\GlobalPatient;
use App\Models\GlobalTreatmentPlan;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;

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

            foreach ($patientData as $patient) {
                GlobalPatient::updateOrCreate(
                    [
                        'patient_id' => $patient->id,
                        'country_id' => $patient->country_id
                    ],
                    [
                        'patient_id' => $patient->id,
                        'gender' => $patient->gender,
                        'date_of_birth' => $patient->date_of_birth,
                        'identity' => $patient->identity,
                        'clinic_id' => $patient->clinic_id,
                        'country_id' => $patient->country_id,
                        'enabled' => $patient->enabled,
                        'deleted_at' => $patient->deleted_at ? Carbon::parse($patient->deleted_at) : $patient->deleted_at,
                    ],
                );
            }

            foreach ($treatmentPlanData as $treatmentPlan) {
                GlobalTreatmentPlan::updateOrCreate(
                    [
                        'treatment_id' => $treatmentPlan->id,
                        'patient_id' => $treatmentPlan->patient_id,
                        'country_id' => $country->id
                    ],
                    [
                        'treatment_id' => $treatmentPlan->id,
                        'name' => $treatmentPlan->name,
                        'patient_id' => $treatmentPlan->patient_id,
                        'country_id' => $country->id,
                        'start_date' => date_create_from_format(config('settings.date_format'), $treatmentPlan->start_date)->format('Y-m-d'),
                        'end_date' => date_create_from_format(config('settings.date_format'), $treatmentPlan->end_date)->format('Y-m-d'),
                        'status' => $treatmentPlan->status,
                    ],
                );
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

        foreach ($patientGlobal as $patient) {
            GlobalPatient::updateOrCreate(
                [
                    'patient_id' => $patient->id,
                    'country_id' => $patient->country_id,
                ],
                [
                    'patient_id' => $patient->id,
                    'gender' => $patient->gender,
                    'date_of_birth' => $patient->date_of_birth,
                    'identity' => $patient->identity,
                    'clinic_id' => $patient->clinic_id,
                    'country_id' => $patient->country_id,
                    'enabled' => $patient->enabled,
                    'deleted_at' => $patient->deleted_at ? Carbon::parse($patient->deleted_at) : $patient->deleted_at,
                ],
            );
        }

        foreach ($treatmentPlanGlobal as $treatmentPlan) {
            $patient = json_decode(Http::withToken($access_token)->get(env('PATIENT_SERVICE_URL') . '/patient/id/' . $treatmentPlan->patient_id));

            GlobalTreatmentPlan::updateOrCreate(
                [
                    'treatment_id' => $treatmentPlan->id,
                    'patient_id' => $treatmentPlan->patient_id,
                    'country_id' => $patient && is_object($patient) ? $patient->country_id : $patient,
                ],
                [
                    'treatment_id' => $treatmentPlan->id,
                    'name' => $treatmentPlan->name,
                    'patient_id' => $treatmentPlan->patient_id,
                    'country_id' => $patient && is_object($patient) ? $patient->country_id : $patient,
                    'start_date' => date_create_from_format(config('settings.date_format'), $treatmentPlan->start_date)->format('Y-m-d'),
                    'end_date' => date_create_from_format(config('settings.date_format'), $treatmentPlan->end_date)->format('Y-m-d'),
                    'status' => $treatmentPlan->status,
                ],
            );
        }

        $this->info('Data has been sync successfully');
    }
}
