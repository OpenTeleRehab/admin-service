<?php

namespace App\Console\Commands;

use App\Models\Organization;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Validator;

class CreateOrganization extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'hi:create-organization {org_name} {admin_email}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Create organization';

    /**
     * Execute the console command.
     *
     * @return bool
     */
    public function handle()
    {
        $adminEmail = $this->argument('admin_email');
        $orgName = $this->argument('org_name');
        $validator = Validator::make([
            'name' => $orgName,
            'admin_email' => $adminEmail,
            'sub_domain_name' => env('APP_NAME'),
        ], [
            'name' => 'unique:organizations,name',
            'admin_email' => 'unique:organizations,admin_email',
            'sub_domain_name' => 'unique:organizations,sub_domain_name',
        ]);

        if ($validator->fails()) {
            foreach ($validator->errors()->all() as $error) {
                $this->error($error);
            }

            return false;
        }

        Organization::create([
            'name' => $orgName,
            'type' => Organization::NON_HI_TYPE,
            'admin_email' => $adminEmail,
            'sub_domain_name' => env('APP_NAME'),
            'max_number_of_therapist' => 10000,
            'max_number_of_phc_worker' => 10000,
            'max_ongoing_treatment_plan' => 35,
            'max_phc_ongoing_treatment_plan' => 35,
            'max_sms_per_week' => 2,
            'max_phc_sms_per_week' => 2,
            'status' => Organization::SUCCESS_ORG_STATUS,
        ]);

        $this->info('Organization has been created successfully');
        return true;
    }
}
