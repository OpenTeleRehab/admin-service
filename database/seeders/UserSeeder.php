<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class UserSeeder extends Seeder
{
    /**
     * Seed the application's database.
     *
     * @return void
     */
    public function run()
    {
        DB::table('users')->insert([
            'first_name' => 'Organization',
            'last_name' => 'Admin',
            'type' => User::ADMIN_GROUP_ORG_ADMIN,
            'email' => 'organization-admin@we.co',
            'password' => bcrypt('organization-admin@we.co'),
        ]);

        DB::table('users')->insert([
            'first_name' => 'Country',
            'last_name' => 'Admin',
            'type' => 'country_admin',
            'email' => 'country-admin@we.co',
            'password' => bcrypt('country-admin@we.co'),
            'country_id' => 1,
        ]);

        DB::table('users')->insert([
            'first_name' => 'Clinic',
            'last_name' => 'Admin',
            'type' => 'clinic_admin',
            'email' => 'clinic-admin@we.co',
            'password' => bcrypt('clinic-admin@we.co'),
            'country_id' => 1,
            'clinic_id' => 1,
        ]);
    }
}
