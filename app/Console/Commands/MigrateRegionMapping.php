<?php

namespace App\Console\Commands;

use App\Helpers\KeycloakHelper;
use App\Models\Clinic;
use App\Models\Country;
use App\Models\Forwarder;
use App\Models\Province;
use App\Models\Region;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Http;

class MigrateRegionMapping extends Command
{
    protected $signature = 'hi:migrate-region-mapping';
    protected $description = 'Migrate data mapping for Countries, Regions, Provinces, Clinics, and Regional Admins';

    public function handle()
    {
        $this->info('Starting data mapping...');

        $data = [
            // [ID, Country, Rehab Service, Province, Region, Regional Admin]
            [38, 'Antarctica', 'Web Essentials', 'Test Province', 'Test Region', 'apps@web-essentials.co'],

            // Benin
            [40, 'Benin', 'HI BENIN', 'Test Ville', 'Test Département', 'donmaurales@yahoo.fr'],
            [40, 'Benin', 'DSI', 'Cotonou', 'Littoral', 'dafaton@yahoo.fr'],
            [40, 'Benin', 'ASIN', 'Cotonou', 'Littoral', 'dafaton@yahoo.fr'],
            [40, 'Benin', 'APDP', 'Cotonou', 'Littoral', 'dafaton@yahoo.fr'],
            [40, 'Benin', 'CNHU-HKM', 'Cotonou', 'Littoral', 'dafaton@yahoo.fr'],
            [40, 'Benin', 'Ex-HIA Cotonou', 'Cotonou', 'Littoral', 'dafaton@yahoo.fr'],
            [40, 'Benin', 'CSVHSET', 'Abomey-Calavi', 'Atlantique', 'p.togni@hi.org'],
            [40, 'Benin', 'HZ-OKT', 'Ouidah', 'Atlantique', 'p.togni@hi.org'],
            [40, 'Benin', 'CHUZ-AS', 'Abomey-Calavi', 'Atlantique', 'p.togni@hi.org'],
            [40, 'Benin', 'CHUD-BA', 'Parakou', 'Borgou', 'mhcapo-chichi@gouv.bj'],
            [40, 'Benin', 'HZ-BOKO', 'Boko', 'Borgou', 'mhcapo-chichi@gouv.bj'],

            // Cambodia
            [36, 'Cambodia', 'PRC Kampong Cham', 'Kampong Cham', 'Kampong Cham PRC', 'c.doung@hi.org'],
            [36, 'Cambodia', 'PRC Siem Reap', 'Siem Reap', 'Siem Reap', 'r.chor@hi.org'],
            [36, 'Cambodia', 'PRC Kratie', 'Kratie', 'Kratie', 'r.chor@hi.org'],
            [36, 'Cambodia', 'PRC Prey Veng', 'Prey Veng', 'Prey Veng', 'r.chor@hi.org'],
            [36, 'Cambodia', 'PRC Takeo', 'Takeo', 'Takeo', 'r.chor@hi.org'],
            [36, 'Cambodia', 'PRC Kien Kleang', 'Phnom Penh', 'Phnom Penh', 'r.chor@hi.org'],
            [36, 'Cambodia', 'KC Provincial Hospital', 'Kampong Cham', 'Kampong Cham PH', 'r.chor@hi.org'],

            // Haiti
            [46, 'Haiti', 'FONTEN', 'Les Cayes', 'Sud', 'nirva.fonten.haiti@gmail.com'],
            [46, 'Haiti', 'FONHARE', 'Ouanaminthe', 'Nord-Est', 'biberlande06@gmail.com'],

            // Lao
            [44, 'Lao People\'s Democratic Republic', 'CMR', null, null, null],

            // Myanmar
            [41, 'Myanmar', 'South East - AC6', null, null, null],
            [41, 'Myanmar', 'SouthEast -CDCS', null, null, null],
            [41, 'Myanmar', 'Kachin -Rehab', null, null, null],
            [41, 'Myanmar', 'MHF- Multi Year', null, null, null],
            [41, 'Myanmar', 'Mdy-EQ-GFFO,DFAT', null, null, null],

            // Rwanda
            [39, 'Rwanda', 'BUSHENGE PROVINCIAL HOSPITAL', 'Western', 'Western', 'm.eliackim@hi.org'],
            [39, 'Rwanda', 'BYUMBA LEVEL 2 TEACHING HOSPITAL', 'Northern', 'Northern', 'mukadaphrose2017@gmail.com'],
            [39, 'Rwanda', 'GAHINI DISTRICT HOSPITAL', 'Eastern', 'Eastern', 'm.eliackim@hi.org'],
            [39, 'Rwanda', 'KIBUYE REFERRAL HOSPITAL', 'Western', 'Western', 'celey9@gmail.com'],
            [39, 'Rwanda', 'NYANZA DISTRICT HOSPITAL', 'Southern', 'Southern', 'm.eliackim@hi.org'],
            [39, 'Rwanda', 'RUHENGERI LEVEL 2 TEACHING HOSPITAL', 'Northern', 'Northern', 'muribene12@gmail.com'],

            // Thailand
            [42, 'Thailand', 'Rehab-Thailand', null, null, null],
            [42, 'Thailand', 'Rehab -Emergency', null, null, null],

            // Viet Nam
            [34, 'Viet Nam', 'Bệnh viện HI', 'HI', 'HI', 'l.ngo@hi.org'],
            [34, 'Viet Nam', 'Bệnh viện PHCN Thừa Thiên Huế', 'Huế', 'Huế', 'l.ngo@hi.org'],
            [34, 'Viet Nam', 'Khoa PHCN - Trường Đại học Y Dược Huế', 'Huế', 'Huế', 'l.ngo@hi.org'],
            [34, 'Viet Nam', 'Khoa PHCN Bệnh viện Đa khoa tỉnh Quảng Trị', 'Quảng Trị', 'Quảng Trị', 'l.ngo@hi.org'],
            [34, 'Viet Nam', 'Khoa PHCN - Bệnh viện Đa khoa tỉnh Đồng Nai', 'Đồng Nai', 'Đồng Nai', 'l.ngo@hi.org'],
            [34, 'Viet Nam', 'Bệnh viện PHCN Hà Nội', 'Hà Nội', 'Hà Nội', 'l.ngo@hi.org'],
            [34, 'Viet Nam', 'Private Clinics', 'Private Clinics', 'Private Clinics', 'l.ngo@hi.org'],
            [34, 'Viet Nam', 'Web Essential', 'Web Essential', 'Web Essential', 'l.ngo@hi.org'],
            [34, 'Viet Nam', 'Test Web Essentials', 'Test Web Essentials', 'Test Web Essentials', 'l.ngo@hi.org'],
            [34, 'Viet Nam', 'Khoa PHCN - BV TW Huế', 'Huế', 'Huế', 'l.ngo@hi.org'],
            [34, 'Viet Nam', 'BV Nam Đông', 'Huế', 'Huế', 'l.ngo@hi.org'],
            [34, 'Viet Nam', 'Trường ĐHKTYT Hải Dương', 'Hải Phòng', 'Hải Phòng', 'l.ngo@hi.org'],
            [34, 'Viet Nam', 'Bệnh viện YHCT&PHCN Bình Định', 'Gia Lai', 'Gia Lai', 'l.ngo@hi.org'],
            [34, 'Viet Nam', 'BV YHCT&PHCN tỉnh Quảng trị', 'Quảng Trị', 'Quảng Trị', 'l.ngo@hi.org'],
            [34, 'Viet Nam', 'Phong khám Olympic An Khang', 'Huế', 'Huế', 'l.ngo@hi.org'],
            [34, 'Viet Nam', 'Bệnh viện Hữu nghị Việt Đức', 'Hà Nội', 'Hà Nội', 'l.ngo@hi.org'],
            [34, 'Viet Nam', 'Bệnh viện Bạch Mai', 'Hà Nội', 'Hà Nội', 'l.ngo@hi.org'],
        ];

        try {
            foreach ($data as $row) {
                [$countryId, $countryName, $rehabServiceName, $provinceName, $regionName, $adminEmails] = $row;
                $this->performMapping($countryId, $countryName, $rehabServiceName, $provinceName, $regionName, $adminEmails, $data);
            }
            $this->info("Mapping Rehab Service Admin region_id");
            $userAdmin = User::where('type', User::ADMIN_GROUP_CLINIC_ADMIN)->get();
            foreach($userAdmin as $user){
                $clinic = Clinic::find($user->clinic_id);
                if(!$clinic){
                    continue;
                }
                $user->region_id = $clinic?->region_id;
                $user->save();
            }
            $this->info('Data mapping completed successfully!');
        } catch (\Exception $e) {
            $this->error('Operation failed: ' . $e->getMessage());
            $this->error($e->getTraceAsString());
        }
    }

    private function performMapping($countryId, $countryName, $rehabServiceName, $provinceName, $regionName, $adminEmails, $data)
    {
        $this->comment("Processing: {$rehabServiceName} ({$countryName})");

        // 1. Find Country and Update PHC Worker Limit
        $country = Country::find($countryId);
        if (!$country) {
            $this->warn("Country not found: {$countryName} (ID: {$countryId}). Skipping...");
            return;
        }

        $therapistCount = $this->getTherapistCountFromRemote('country', $countryId);

        if ($country->therapist_limit < $therapistCount) {
            $country->therapist_limit = $therapistCount;
        }

        $country->phc_worker_limit = $country->therapist_limit;
        $country->save();
        $this->info("Updated Country: {$countryName} (ID: {$countryId}) - Limits set to {$therapistCount}");


        // 2. Find or create Region
        if (!$regionName) {
            $this->warn("Region name is missing for country: {$countryName} (ID: {$countryId}). Skipping...");
            return;
        }

        $region = Region::firstOrCreate(
            ['name' => $regionName, 'country_id' => $countryId],
            ['therapist_limit' => 0, 'phc_worker_limit' => 0]
        );

        // 3. Find or Create Province
        if (!$provinceName) {
            $this->warn("Province name is missing for country: {$countryName} (ID: {$countryId}). Skipping...");
            return;
        }

        $province = Province::firstOrCreate(
            ['name' => $provinceName, 'region_id' => $region->id],
            ['therapist_limit' => 0, 'phc_worker_limit' => 0]
        );

        // 4. Update or Create Clinic
        $clinic = Clinic::where('name', $rehabServiceName)->first();
        if ($clinic) {
            $clinic->country_id = $countryId;
            $clinic->region_id = $region->id;
            $clinic->province_id = $province->id;

            $therapistCount = $this->getTherapistCountFromRemote('rehab_service', $clinic->id);
            if ($clinic->therapist_limit < $therapistCount) {
                $clinic->therapist_limit = $therapistCount;
            }
            $clinic->save();
            $this->info("Updated Clinic: {$rehabServiceName}. Therapist Limit updated to {$therapistCount}");
        } else {
            $this->warn("Clinic not found: {$rehabServiceName}");
            return;
        }

        // 5. Find or Create Regional Admins
        foreach (Arr::wrap($adminEmails) as $adminEmail) {
            if (!$adminEmail) {
                continue;
            }

            $regionalAdmin = User::where('email', $adminEmail)->first();

            if (!$regionalAdmin) {
                $regionalAdmin = User::create([
                    'email' => $adminEmail,
                    'first_name' => explode('.', explode('@', $adminEmail)[0])[0],
                    'last_name' => 'Admin',
                    'type' => User::ADMIN_GROUP_REGIONAL_ADMIN,
                    'country_id' => $countryId,
                    'enabled' => true,
                    'language_id' => $country->language_id,
                    'gender' => 'other',
                ]);
                $this->info("Created Regional Admin: {$adminEmail}");
            } else {
                if ($regionalAdmin->type !== User::ADMIN_GROUP_REGIONAL_ADMIN) {
                    $regionalAdmin->type = User::ADMIN_GROUP_REGIONAL_ADMIN;
                    $regionalAdmin->save();
                    $this->info("Updated {$adminEmail} type to Regional Admin");
                }
            }

            // Attach Region to Admin
            $regionalAdmin->regions()->syncWithoutDetaching($region->id);
            $this->info("Region {$regionName} ensured for Admin {$regionalAdmin->email}");

            // Sync to Keycloak only if newly created
            if ($regionalAdmin->wasRecentlyCreated) {
                try {
                    $keycloakUser = KeycloakHelper::getKeycloakUserByUsername($adminEmail);

                    if (!$keycloakUser) {
                        KeycloakHelper::createUser(
                            $regionalAdmin,
                            null,
                            false,
                            User::ADMIN_GROUP_REGIONAL_ADMIN
                        );
                        $this->info("Synced {$adminEmail} to Keycloak successfully.");
                    } else {
                        $this->info("User {$adminEmail} already exists in Keycloak, skipping creation.");
                    }
                } catch (\Exception $e) {
                    $this->warn("Failed to sync {$adminEmail} to Keycloak: " . $e->getMessage());
                }
            }
        }

        // 6. Update Users (Therapists/Patients) in other services
        try {
            $therapistToken = Forwarder::getAccessToken(Forwarder::THERAPIST_SERVICE);

            // For patient service, we need the country iso code
            $countryIsoCode = $country->iso_code;

            if ($countryIsoCode) {
                $patientToken = Forwarder::getAccessToken(Forwarder::PATIENT_SERVICE, $countryIsoCode);

                // Update Patients
                $response = Http::withHeaders([
                    'Authorization' => 'Bearer ' . $patientToken,
                    'country' => $countryIsoCode,
                    'Accept' => 'application/json',
                ])->post(env('PATIENT_SERVICE_URL') . '/data-clean-up/users/update', [
                    'entity_name' => 'rehab_service',
                    'entity_id' => $clinic->id,
                    'region_id' => $region->id,
                    'province_id' => $province->id,
                ]);

                if ($response->successful()) {
                    $this->info("Triggered patient-service update for clinic {$clinic->name}");
                } else {
                    $this->warn("Failed to trigger patient-service update for clinic {$clinic->name}: " . $response->body());
                }
            } else {
                $this->warn("Skipping patient-service update for clinic {$clinic->name} because iso_code is missing for country {$country->name}");
            }

            // Update Therapists
            $response = Http::withHeaders([
                'Accept' => 'application/json',
            ])->withToken($therapistToken)->post(env('THERAPIST_SERVICE_URL') . '/data-clean-up/users/update', [
                'entity_name' => 'rehab_service',
                'entity_id' => $clinic->id,
                'region_id' => $region->id,
                'province_id' => $province->id,
            ]);

            if ($response->successful()) {
                $this->info("Triggered therapist-service update for clinic {$clinic->name}");
            } else {
                $this->warn("Failed to trigger therapist-service update for clinic {$clinic->name}: " . $response->body());
            }

        } catch (\Exception $e) {
            $this->warn("Exception during cross-service user update for clinic {$clinic->name}: " . $e->getMessage());
        }

        // 7. Update Province Therapist Limit & PHC Limit
        $province = Province::where('name', $provinceName)->first();
        $pTherapistCount = 0;
        foreach ($data as $dRow) {
            $dEffectiveProvince = $dRow[3]; // Get Province
            $dEffectiveRegion = $dRow[4];   // Get Region
            if ($dRow[0] == $countryId && $dEffectiveRegion == $regionName && $dEffectiveProvince == $provinceName) {
                $dClinic = Clinic::where('name', $dRow[2])->first();
                if ($dClinic) {
                    $pTherapistCount += $this->getTherapistCountFromRemote('rehab_service', $dClinic->id);
                }
            }
        }
        $province->therapist_limit = $pTherapistCount;
        $province->phc_worker_limit = $pTherapistCount;
        $province->save();
        $this->info("Updated Province: {$provinceName} - Limits set to {$pTherapistCount}");

        // 8. Update Region Therapist Limit & PHC Limit
        $rTherapistCount = 0;
        foreach ($data as $dRow) {
            $dEffectiveRegion = $dRow[4];   // Get Region
            if ($dRow[0] == $countryId && $dEffectiveRegion == $regionName) {
                $dClinic = Clinic::where('name', $dRow[2])->first();
                if ($dClinic) {
                    $rTherapistCount += $this->getTherapistCountFromRemote('rehab_service', $dClinic->id);
                }
            }
        }
        $region->therapist_limit = $rTherapistCount;
        $region->phc_worker_limit = $rTherapistCount;
        $region->save();
        $this->info("Updated Region: {$regionName} - Limits set to {$rTherapistCount}");
    }

    private function getTherapistCountFromRemote($entityName, $entityId)
    {
        try {
            $therapistToken = Forwarder::getAccessToken(Forwarder::THERAPIST_SERVICE);

            $response = Http::withToken($therapistToken)->get(
                env('THERAPIST_SERVICE_URL') . '/data-clean-up/users/count',
                [
                    'entity_name' => $entityName,
                    'entity_id' => $entityId,
                    'user_type' => User::GROUP_THERAPIST,
                ]
            );

            if ($response->successful()) {
                return $response->json('data', 0);
            } else {
                $this->warn("Failed to fetch therapist count for {$entityName} ID {$entityId}: " . $response->body());
                return 0;
            }
        } catch (\Exception $e) {
            $this->warn("Exception fetching therapist count for {$entityName} ID {$entityId}: " . $e->getMessage());
            return 0;
        }
    }
}
