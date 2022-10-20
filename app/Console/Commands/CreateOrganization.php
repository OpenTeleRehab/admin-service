<?php

namespace App\Console\Commands;

use App\Models\Organization;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;

class CreateOrganization extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'hi:create-organization';

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
        $system_limit = json_decode(Storage::get('system_limit/settings.json'));
        $user = User::where('type', User::ADMIN_GROUP_ORG_ADMIN)->first();

        if ($user) {
            Organization::create([
                'name' => 'hi',
                'type' => Organization::HI_TYPE,
                'admin_email' => $user->email,
                'sub_domain_name' => 'hi',
                'max_number_of_therapist' => $system_limit->therapist_content_limit,
                'max_ongoing_treatment_plan' => $system_limit->number_of_ongoing_treatment_per_therapist,
                'status' => Organization::SUCCESS_ORG_STATUS,
                'created_by' => 0,
            ]);

            $this->info('Organization has been created successfully');
            return true;
        }

        $this->error('There is no Organization Admin user found');
        return false;
    }
}
