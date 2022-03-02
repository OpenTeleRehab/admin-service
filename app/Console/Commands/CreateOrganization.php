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
     * Create a new command instance.
     *
     * @return void
     */
    public function __construct()
    {
        parent::__construct();
    }

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle()
    {
        $system_limit = json_decode(Storage::get('system_limit/settings.json'));
        $user = User::where('type', User::ADMIN_GROUP_GLOBAL_ADMIN)->first();

        if ($user) {
            Organization::create([
                'name' => 'Humanity Inclusion',
                'type' => Organization::HI_TYPE,
                'admin_email' => $user->email,
                'sub_domain_name' => 'hi',
                'max_number_of_therapist' => $system_limit->therapist_content_limit,
                'max_ongoing_treatment_plan' => $system_limit->number_of_ongoing_treatment_per_therapist,
            ]);
        }

        $this->info('Organization has been created successfully');

        return 0;
    }
}
