<?php

namespace App\Console\Commands;

use App\Models\Organization;
use App\Models\User;
use Illuminate\Console\Command;

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
        $user = User::where('type', User::ADMIN_GROUP_GLOBAL_ADMIN)->first();
        if ($user) {
            Organization::create([
                'name' => 'Humanity Inclusion',
                'type' => Organization::HI_TYPE,
                'admin_email' => $user->email,
                'sub_domain_name' => 'hi',
            ]);
        }

        $this->info('Organization has been created successfully');

        return 0;
    }
}
