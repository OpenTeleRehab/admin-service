<?php

namespace App\Console\Commands;

use App\Models\User;
use App\Helpers\KeycloakHelper;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class CreateSuperAdminUser extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'hi:create-super-admin-user {email} {first_name} {last_name}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Create super admin user';

    /**
     * @return bool
     * @throws \Illuminate\Http\Client\RequestException
     */
    public function handle()
    {
        DB::beginTransaction();

        $email = $this->argument('email');
        $firstName = $this->argument('first_name');
        $lastName = $this->argument('last_name');
        $type = User::ADMIN_GROUP_SUPER_ADMIN;

        if (User::where('email', $email)->exists()) {
            $this->info('This email is already exists');
            return false;
        }

        $user = User::create([
            'email' => $email,
            'first_name' => $firstName,
            'last_name' => $lastName,
            'type' => $type,
            'country_id' => null,
            'clinic_id' => null,
            'language_id' => null,
            'enabled' => true,
        ]);

        if (!$user) {
            $this->info('This user is unable to create on system');
            return false;
        }

        try {
            KeycloakHelper::createUser($user, '', false, $type);
        } catch (\Exception $e) {
            DB::rollBack();
            $this->info('This user is unable to create on keycloak');
            return false;
        }

        DB::commit();
        $this->info('User has been created successfully');

        return true;
    }
}
