<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;

class AlterGlobalAdminRole extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'hi:change-global-admin-to-organization-admin';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Change global admin to organization admin';

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
        User::where('type', User::ADMIN_GROUP_GLOBAL_ADMIN)->update(['type' => User::ADMIN_GROUP_ORG_ADMIN]);

        $this->info('User role has been updated successfully');

        return 0;
    }
}
