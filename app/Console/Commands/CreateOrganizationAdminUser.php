<?php

namespace App\Console\Commands;

use App\Models\User;
use App\Helpers\KeycloakHelper;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;

class CreateOrganizationAdminUser extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'hi:create-organization-admin-user {email} {org_name}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Create organization admin user';

    /**
     * @return bool
     * @throws \Illuminate\Http\Client\RequestException
     */
    public function handle()
    {
        DB::beginTransaction();

        $email = $this->argument('email');
        $org_name = $this->argument('org_name');
        $type = User::ADMIN_GROUP_ORG_ADMIN;

        if (User::where('email', $email)->exists()) {
            $this->info('This email is already exists');
            return false;
        }

        $user = User::create([
            'email' => $email,
            'first_name' => $org_name,
            'last_name' => $org_name,
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
            self::createKeycloakUser($org_name, $email, $type);
        } catch (\Exception $e) {
            DB::rollBack();
            $this->info('This user is unable to create on keycloak');
            return false;
        }

        DB::commit();
        $this->info('User has been created successfully');

        return 0;
    }

    /**
     * @param string $org_name
     * @param string $email
     * @param string $userGroup
     *
     * @return false|mixed|string
     * @throws \Exception
     */
    private function createKeycloakUser($org_name, $email, $userGroup)
    {
        $token = KeycloakHelper::getKeycloakAccessToken();

        if ($token) {
            try {
                $response = Http::withToken($token)->withHeaders([
                    'Content-Type' => 'application/json'
                ])->post(KEYCLOAK_USER_URL, [
                    'username' => $email,
                    'email' => $email,
                    'enabled' => true,
                    'firstName' => $org_name,
                    'lastName' => $org_name,
                    'attributes' => [
                        'locale' => ['en']
                    ]
                ]);

                if ($response->successful()) {
                    $createdUserUrl = $response->header('Location');
                    $lintArray = explode('/', $createdUserUrl);
                    $userKeycloakUuid = end($lintArray);
                    $isCanSetPassword = true;
                    $isCanAssignUserToGroup = self::assignUserToGroup($token, $createdUserUrl, $userGroup);

                    if ($isCanSetPassword && $isCanAssignUserToGroup) {
                        KeycloakHelper::sendEmailToNewUser($userKeycloakUuid);
                        return $userKeycloakUuid;
                    }
                }
            } catch (\Exception $e) {
                throw new \Exception($e->getMessage());
            }
        }
        throw new \Exception('no_token');
    }

    /**
     * @param string $token
     * @param string $userUrl
     * @param string $userGroup
     * @param false $isUnassigned
     *
     * @return bool
     */
    private static function assignUserToGroup($token, $userUrl, $userGroup, $isUnassigned = false)
    {
        $userGroups = KeycloakHelper::getUserGroups($token);
        $url = $userUrl . '/groups/' . $userGroups[$userGroup];

        if ($isUnassigned) {
            $response = Http::withToken($token)->delete($url);
        } else {
            $response = Http::withToken($token)->put($url);
        }

        if ($response->successful()) {
            return true;
        }

        return false;
    }
}
