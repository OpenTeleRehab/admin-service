<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;
use Spatie\Activitylog\Facades\Activity;

class CreateBackendUser extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'hi:create-backend-user';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Create backend user';

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
        // Disable activity logging for backend user creation
        Activity::disableLogging();

        User::updateOrCreate(
            [
                'email' => env('KEYCLOAK_BACKEND_CLIENT'),
            ],
            [
                'first_name' => 'DO NOT DELETE!',
                'last_name' => 'DO NOT DELETE!',
                'type' => '',
                'enabled' => 1,
            ]
        );

        // Re-enable activity logging after backend user creation
        Activity::enableLogging();

        $this->info('Backend user has been created or updated successfully');

        return true;
    }
}
