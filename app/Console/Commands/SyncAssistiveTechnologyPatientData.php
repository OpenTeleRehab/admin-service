<?php

namespace App\Console\Commands;

use App\Models\Forwarder;
use App\Models\GlobalAssistiveTechnologyPatient;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;

class SyncAssistiveTechnologyPatientData extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'hi:sync-assistive-technology-patient-data';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Sync assistive technology provided patient data from global and vietnam hosts';

    /**
     * @return void
     * @throws \Illuminate\Http\Client\RequestException
     */
    public function handle()
    {
        $hosts = config('settings.hosting_country');

        // Sync assistive technology provided patient from vn db or other country to new table.
        foreach ($hosts as $host) {
            $access_token = Forwarder::getAccessToken(Forwarder::PATIENT_SERVICE, $host);
            $response = Http::withHeaders(['Authorization' => 'Bearer ' . $access_token, 'country' => $host])->get(env('PATIENT_SERVICE_URL') . '/assistive-technologies/get-at-patient');

            if (!empty($response) && $response->successful()) {
                $patientData = $response->json();
                foreach ($patientData as $patient) {
                    GlobalAssistiveTechnologyPatient::updateOrCreate(
                        [
                            'patient_id' => $patient['id'],
                            'country_id' => $patient['country_id'],
                            'assistive_technology_id' => $patient['assistive_technology_id'],
                        ],
                        [
                            'patient_id' => $patient['id'],
                            'gender' => $patient['gender'],
                            'date_of_birth' => $patient['date_of_birth'],
                            'identity' => $patient['identity'],
                            'clinic_id' => $patient['clinic_id'],
                            'country_id' => $patient['country_id'],
                            'enabled' => $patient['enabled'],
                            'therapist_id' => $patient['therapist_id'],
                            'assistive_technology_id' => $patient['assistive_technology_id'],
                            'provision_date' => $patient['provision_date'],
                            'deleted_at' => $patient['deleted_at'] ? Carbon::parse($patient['deleted_at']) : $patient['deleted_at'],
                        ],
                    );
                }
            }
        }

        // Sync assistive technology provided patient from global db to new table.
        $access_token = Forwarder::getAccessToken(Forwarder::PATIENT_SERVICE);
        $response = Http::withToken($access_token)->get(env('PATIENT_SERVICE_URL') . '/assistive-technologies/get-at-patient');

        if (!empty($response) && $response->successful()) {
            $patientGlobal = $response->json();
            foreach ($patientGlobal as $patient) {
                GlobalAssistiveTechnologyPatient::updateOrCreate(
                    [
                        'patient_id' => $patient['id'],
                        'country_id' => $patient['country_id'],
                        'assistive_technology_id' => $patient['assistive_technology_id'],
                    ],
                    [
                        'patient_id' => $patient['id'],
                        'gender' => $patient['gender'],
                        'date_of_birth' => $patient['date_of_birth'],
                        'identity' => $patient['identity'],
                        'clinic_id' => $patient['clinic_id'],
                        'country_id' => $patient['country_id'],
                        'enabled' => $patient['enabled'],
                        'therapist_id' => $patient['therapist_id'],
                        'assistive_technology_id' => $patient['assistive_technology_id'],
                        'provision_date' => $patient['provision_date'],
                        'deleted_at' => $patient['deleted_at'] ? Carbon::parse($patient['deleted_at']) : $patient['deleted_at'],
                    ],
                );
            }
        }

        $this->info('Data has been sync successfully');
    }
}
