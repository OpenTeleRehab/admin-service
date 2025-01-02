<?php

namespace App\Console\Commands;

use App\Models\Country;
use App\Models\Forwarder;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;

class SyncPatientData extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'hi:sync-patient-twilio-call-data {--all}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Sync patient twilio call data from twilio to patient db for each hosts';

    /**
     * @return void
     * @throws \Illuminate\Http\Client\RequestException
     */
    public function handle()
    {
        $hosts = config('settings.hosting_country');
        $isoCodes = array_values($hosts);
        $hostCountryIds = Country::whereIn('iso_code', $isoCodes)->pluck('id')->toArray();
        
        // Sync twilio data to vn db or other country host db.
        foreach ($hosts as $host) {
            $country = Country::where('iso_code', $host)->first();
            $access_token = Forwarder::getAccessToken(Forwarder::PATIENT_SERVICE, $host);

            if ($this->option('all')) {
                // Sync all records.
                Http::withHeaders(['Authorization' => 'Bearer ' . $access_token, 'country' => $host])->post(env('PATIENT_SERVICE_URL') . '/call-history', ['all' => true, 'country_id' => $country->id]);
            } else {
                // Sync only yesterday records.
                Http::withHeaders(['Authorization' => 'Bearer ' . $access_token, 'country' => $host])->post(env('PATIENT_SERVICE_URL') . '/call-history', ['country_id' => $country->id]);
            }
        }

        // Sync patient call data for global db.
        $access_token = Forwarder::getAccessToken(Forwarder::PATIENT_SERVICE);

        if ($this->option('all')) {
            // Sync all records.
            Http::withToken($access_token)->post(env('PATIENT_SERVICE_URL') . '/call-history', ['all' => true, 'host_country_ids' => $hostCountryIds]);
        } else {
            // Sync only yesterday records.
            Http::withToken($access_token)->post(env('PATIENT_SERVICE_URL') . '/call-history', ['host_country_ids' => $hostCountryIds]);
        }

        $this->info('Data has been sync successfully');
    }
}
