<?php

namespace App\Helpers;

use Carbon\Carbon;
use Firebase\JWT\JWT;
use App\Models\Language;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Cache;

define("KEYCLOAK_USER_URL", env('KEYCLOAK_URL') . '/auth/admin/realms/' . env('KEYCLOAK_REAMLS_NAME') . '/users');
define("KEYCLOAK_TOKEN_URL", env('KEYCLOAK_URL') . '/auth/realms/' . env('KEYCLOAK_REAMLS_NAME') . '/protocol/openid-connect/token');
define("KEYCLOAK_GROUPS_URL", env('KEYCLOAK_URL') . '/auth/admin/realms/' . env('KEYCLOAK_REAMLS_NAME') . '/groups');
define('KEYCLOAK_ROLES_URL', env('KEYCLOAK_URL') . '/auth/admin/realms/' . env('KEYCLOAK_REAMLS_NAME') . '/roles');
define("KEYCLOAK_EXECUTE_EMAIL", '/execute-actions-email?client_id=' . env('KEYCLOAK_BACKEND_CLIENT') . '&redirect_uri=' . env('REACT_APP_BASE_URL'));

define("GADMIN_KEYCLOAK_TOKEN_URL", env('KEYCLOAK_URL') . '/auth/realms/' . env('GADMIN_KEYCLOAK_REAMLS_NAME') . '/protocol/openid-connect/token');
define("THERAPIST_KEYCLOAK_TOKEN_URL", env('KEYCLOAK_URL') . '/auth/realms/' . env('THERAPIST_KEYCLOAK_REAMLS_NAME') . '/protocol/openid-connect/token');
define("PATIENT_LOGIN_URL", env('PATIENT_SERVICE_URL') . '/auth/login');
define("WEBHOOK_URL", env('KEYCLOAK_URL') . '/auth/realms/' . env('KEYCLOAK_REAMLS_NAME') . '/webhooks');

/**
 * Class KeycloakHelper
 * @package App\Helpers
 */
class KeycloakHelper
{
    const GADMIN_ACCESS_TOKEN = 'gadmin_access_token';
    const ADMIN_ACCESS_TOKEN = 'admin_access_token';
    const THERAPIST_ACCESS_TOKEN = 'therapist_access_token';
    const PATIENT_ACCESS_TOKEN = 'patient_access_token';
    const VN_PATIENT_ACCESS_TOKEN = 'vn_patient_access_token';

    /**
     * @return mixed|null
     */
    public static function getKeycloakAccessToken()
    {
        $access_token = Cache::get(self::ADMIN_ACCESS_TOKEN);

        if ($access_token) {
            $token_arr = explode('.', $access_token);
            $token_obj = json_decode(JWT::urlsafeB64Decode($token_arr[1]), true);
            $token_exp_at = $token_obj['exp'];
            $current_timestamp = Carbon::now()->timestamp;

            if ($current_timestamp < $token_exp_at) {
                return $access_token;
            }
        }

        return self::generateKeycloakToken(KEYCLOAK_TOKEN_URL, env('KEYCLOAK_BACKEND_SECRET'), self::ADMIN_ACCESS_TOKEN);
    }

    /**
     * @return mixed|null
     */
    public static function getGAdminKeycloakAccessToken()
    {
        $access_token = Cache::get(self::GADMIN_ACCESS_TOKEN);

        if ($access_token) {
            $token_arr = explode('.', $access_token);
            $token_obj = json_decode(JWT::urlsafeB64Decode($token_arr[1]), true);
            $token_exp_at = $token_obj['exp'];
            $current_timestamp = Carbon::now()->timestamp;

            if ($current_timestamp < $token_exp_at) {
                return $access_token;
            }
        }

        return self::generateKeycloakToken(GADMIN_KEYCLOAK_TOKEN_URL, env('GADMIN_KEYCLOAK_BACKEND_SECRET'), self::GADMIN_ACCESS_TOKEN);
    }

    /**
     * @return mixed|null
     */
    public static function getTherapistKeycloakAccessToken()
    {
        $access_token = Cache::get(self::THERAPIST_ACCESS_TOKEN);

        if ($access_token) {
            $token_arr = explode('.', $access_token);
            $token_obj = json_decode(JWT::urlsafeB64Decode($token_arr[1]), true);
            $token_exp_at = $token_obj['exp'];
            $current_timestamp = Carbon::now()->timestamp;

            if ($current_timestamp > $token_exp_at) {
                return self::generateKeycloakToken(THERAPIST_KEYCLOAK_TOKEN_URL, env('THERAPIST_KEYCLOAK_BACKEND_SECRET'), self::THERAPIST_ACCESS_TOKEN);
            }

            return $access_token;
        }

        return self::generateKeycloakToken(THERAPIST_KEYCLOAK_TOKEN_URL, env('THERAPIST_KEYCLOAK_BACKEND_SECRET'), self::THERAPIST_ACCESS_TOKEN);
    }

    /**
     * @param string|null $host
     *
     * @return mixed|null
     */
    public static function getPatientKeycloakAccessToken($host)
    {
        $cache_key = $host === config('settings.hosting_country.vn_country_iso') ? self::VN_PATIENT_ACCESS_TOKEN : self::PATIENT_ACCESS_TOKEN;
        $access_token = Cache::get($cache_key);

        if ($access_token) {
            $token_arr = explode('.', $access_token);
            $token_obj = json_decode(JWT::urlsafeB64Decode($token_arr[1]), true);
            $token_exp_at = (int) $token_obj['exp'];
            $current_timestamp = Carbon::now()->timestamp;

            if ($current_timestamp < $token_exp_at) {
                return $access_token;
            }
        }

        $response = Http::withHeaders(['country' => $host])->post(PATIENT_LOGIN_URL, [
            'email' => env('KEYCLOAK_BACKEND_CLIENT'),
            'pin' => env('PATIENT_BACKEND_PIN'),
        ]);

        if ($response->successful()) {
            $result = $response->json();

            Cache::forever($cache_key, $result['data']['token']);

            return $result['data']['token'];
        }

        return null;
    }

    /**
     * @param string $username
     * @param string $password
     *
     * @return \Illuminate\Http\Client\Response
     */
    public static function getLoginUser($username, $password)
    {
        return Http::asForm()->post(KEYCLOAK_TOKEN_URL, [
            'grant_type' => 'password',
            'client_id' => env('KEYCLOAK_BACKEND_CLIENT'),
            'client_secret' => env('KEYCLOAK_BACKEND_SECRET'),
            'username' => $username,
            'password' => $password,
        ]);
    }

    /**
     * @param string $token
     * @param string $url
     * @param string $password
     * @param bool $isTemporary
     *
     * @return bool
     */
    public static function resetUserPassword($token, $url, $password, $isTemporary = true)
    {
        $response = Http::withToken($token)->put($url . '/reset-password', [
            'value' => $password,
            'type' => 'password',
            'temporary' => $isTemporary
        ]);
        if ($response->successful()) {
            return true;
        }
        return false;
    }

    /**
     * @param string $token
     * @param string $url
     *
     * @return bool
     */
    public static function deleteUser($token, $url)
    {
        $response = Http::withToken($token)->delete($url);

        if ($response->successful()) {
            return true;
        }

        return false;
    }

    /**
     * @param string $role
     *
     * @return bool
     */
    public static function hasRealmRole($roles)
    {
        $decodedToken = json_decode(Auth::token(), true);

        if (!isset($decodedToken['realm_access']['roles'])) {
            return false;
        }

        $authRoles = $decodedToken['realm_access']['roles'];

        return !empty(array_intersect($roles, $authRoles));
    }

    /**
     * @param \App\Models\User $user
     * @param string $password
     * @param bool $isTemporaryPassword
     * @param string $userGroup
     *
     * @return false|mixed|string
     * @throws \Exception
     */
    public static function createUser($user, $password, $isTemporaryPassword, $userGroup)
    {
        $token = self::getKeycloakAccessToken();

        if ($token) {
            try {
                $language = Language::find(Auth::user()->language_id);
                $languageCode = $language ? $language->code : null;
                $attributes = [];

                if ($languageCode) {
                    $attributes['locale'] = [$languageCode];
                }

                $response = Http::withToken($token)->withHeaders([
                    'Content-Type' => 'application/json'
                ])->post(KEYCLOAK_USER_URL, [
                    'username' => $user->email,
                    'email' => $user->email,
                    'firstName' => $user->first_name,
                    'lastName' => $user->last_name,
                    'enabled' => true,
                    'attributes' => $attributes,
                ]);

                if ($response->successful()) {
                    $createdUserUrl = $response->header('Location');
                    $lintArray = explode('/', $createdUserUrl);
                    $userKeycloakUuid = end($lintArray);
                    $isCanSetPassword = true;

                    if ($password) {
                        $isCanSetPassword = KeycloakHelper::resetUserPassword(
                            $token,
                            $createdUserUrl,
                            $password,
                            $isTemporaryPassword
                        );
                    }

                    $isCanAssignUserToGroup = self::assignUserToGroup($token, $createdUserUrl, $userGroup);

                    if ($isCanSetPassword && $isCanAssignUserToGroup) {
                        $federatedDomains = array_map(fn($d) => strtolower(trim($d)), explode(',', env('FEDERATED_DOMAINS', '')));
                        $email = strtolower($user->email);

                        if (Str::endsWith($email, $federatedDomains)) {
                            $emailSendingData = [
                                'subject' => 'Welcome to OpenTeleRehab',
                                'name' => $user->last_name . ' ' . $user->first_name,
                                'link' => env('REACT_APP_BASE_URL')
                            ];

                            Mail::send('federatedUser.mail', $emailSendingData, function ($message) use ($user, $emailSendingData) {
                                $message->to($user->email)
                                    ->subject($emailSendingData['subject']);
                            });
                        } else {
                            self::sendEmailToNewUser($userKeycloakUuid);
                        }

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
     * @param string $userId
     *
     * @return \Illuminate\Http\Client\Response
     */
    public static function sendEmailToNewUser($userId)
    {
        $token = KeycloakHelper::getKeycloakAccessToken();
        $url = KEYCLOAK_USER_URL . '/' . $userId . KEYCLOAK_EXECUTE_EMAIL;
        return Http::withToken($token)->put($url, ['UPDATE_PASSWORD']);
    }

    /**
     * @param string $url
     * @param string $client_secret
     * @param string $cache_key
     *
     * @return void
     */
    private static function generateKeycloakToken($url, $client_secret, $cache_key)
    {
        $response = Http::asForm()->post($url, [
            'grant_type' => 'password',
            'client_id' => env('KEYCLOAK_BACKEND_CLIENT'),
            'client_secret' => $client_secret,
            'username' => env('KEYCLOAK_BACKEND_USERNAME'),
            'password' => env('KEYCLOAK_BACKEND_PASSWORD'),
            'scope' => 'openid profile email'
        ]);

        if ($response->successful()) {
            $result = $response->json();

            Cache::forever($cache_key, $result['access_token']);

            return $result['access_token'];
        }

        return null;
    }

    /**
     * @param string $token
     * @param string $userUrl
     * @param string $userGroup
     * @param false $isUnassigned
     *
     * @return bool
     */
    public static function assignUserToGroup($token, $userUrl, $userGroup, $isUnassigned = false)
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

    /**
     * @return string
     */
    public static function getUserUuid()
    {
        $decodedToken = json_decode(Auth::token(), true);
        return $decodedToken['sub'];
    }

    /**
     * @return array
     */
    public static function getUserGroup()
    {
        $authUser = Auth::user();
        $userGroupUrl = KEYCLOAK_USER_URL . '/' . $authUser->keycloak_user_uuid . '/groups';
        $token = self::getKeycloakAccessToken();
        $response = Http::withToken($token)->get($userGroupUrl);
        $userGroups = [];
        if ($response->successful()) {
            $groups = $response->json();
            foreach ($groups as $group) {
                array_push($userGroups, $group['name']);
            }
        }

        return $userGroups;
    }

    /**
     * @param string $token
     *
     * @return array
     */
    public static function getUserGroups($token)
    {
        $response = Http::withToken($token)->get(KEYCLOAK_GROUPS_URL);
        $userGroups = [];
        if ($response->successful()) {
            $groups = $response->json();
            foreach ($groups as $group) {
                $userGroups[$group['name']] = $group['id'];
            }
        }

        return $userGroups;
    }

    /**
     * @param string $email
     *
     * @return \GuzzleHttp\Promise\PromiseInterface|\Illuminate\Http\Client\Response
     */
    public static function getUser($email)
    {
        $token = KeycloakHelper::getKeycloakAccessToken();
        $response = Http::withToken($token)->withHeaders([
            'Content-Type' => 'application/json'
        ])->get(KEYCLOAK_USER_URL, [
            'email' => $email,
        ]);

        return $response;
    }

    /**
     * @param string $url
     * @param array $eventTypes
     *
     * @return bool
     */
    public static function createWebhook($url, $eventTypes)
    {
        $response = Http::asForm()->post(KEYCLOAK_TOKEN_URL, [
            'grant_type' => 'password',
            'client_id' => env('KEYCLOAK_BACKEND_CLIENT'),
            'client_secret' => env('KEYCLOAK_BACKEND_SECRET'),
            'username' => env('KEYCLOAK_BACKEND_USERNAME'),
            'password' => env('KEYCLOAK_BACKEND_PASSWORD')
        ]);
        $token = null;
        if ($response->successful()) {
            $result = $response->json();
            $token = $result['access_token'];
        }

        if ($token) {
            $response = Http::withToken($token)->withHeaders([
                'Content-Type' => 'application/json'
            ])->post(WEBHOOK_URL, [
                'enabled' => true,
                'url' => $url,
                'secret' => env('KEYCLOAK_BACKEND_SECRET'),
                'eventTypes' => $eventTypes,
            ]);
            if ($response->successful()) {
                return true;
            }
        }

        return false;
    }

    /**
     * Create a realm role in Keycloak.
     *
     * @param string $roleName
     * @param string $description
     * @return bool
     */
    public static function createRealmRole($roleName, $description = '')
    {
        $token = self::getKeycloakAccessToken();

        $response = Http::withToken($token)
            ->withHeaders(['Content-Type' => 'application/json'])
            ->post(KEYCLOAK_ROLES_URL, [
                'name' => $roleName,
                'description' => $description,
            ]);

        return $response->successful();
    }

    /**
     * Create a new group in Keycloak.
     *
     * @param string $groupName
     * @return bool
     */
    public static function createGroup($groupName)
    {
        $token = self::getKeycloakAccessToken();

        $response = Http::withToken($token)
            ->withHeaders(['Content-Type' => 'application/json'])
            ->post(KEYCLOAK_GROUPS_URL, ['name' => $groupName]);

        return $response->successful();
    }

    /**
     * Assign a realm role to a Keycloak group.
     *
     * @param string $groupName
     * @param string $roleName
     * @return bool
     */
    public static function assignRealmRoleToGroup($groupName, $roleName)
    {
        $token = self::getKeycloakAccessToken();
        $groupId = self::getUserGroups($token)[$groupName] ?? null;

        if (!$groupId) {
            throw new \Exception("Group '{$groupName}' not found.");
        }

        $roleResponse = Http::withToken($token)
            ->get(KEYCLOAK_ROLES_URL . "/{$roleName}");

        if (!$roleResponse->successful()) {
            throw new \Exception("Role '{$roleName}' not found.");
        }

        $role = $roleResponse->json();

        $assignResponse = Http::withToken($token)
            ->withHeaders(['Content-Type' => 'application/json'])
            ->post(KEYCLOAK_GROUPS_URL . "/{$groupId}/role-mappings/realm", [
                [
                    'id' => $role['id'],
                    'name' => $role['name']
                ]
            ]);

        return $assignResponse->successful();
    }

    /**
     * Get a Keycloak user by username.
     *
     * @param string $username
     * @return array|null
     */
    public static function getKeycloakUserByUsername(string $username)
    {
        $token = self::getKeycloakAccessToken();
        if (!$token) {
            return null;
        }

        $response = Http::withToken($token)->withHeaders([
            'Content-Type' => 'application/json'
        ])->get(KEYCLOAK_USER_URL, [
            'username' => $username,
        ]);

        if ($response->successful()) {
            return $response->json()[0] ?? null;
        }

        return null;
    }

    /**
     * Set a Keycloak user attributes.
     *
     * @param string $id Keycloak user UUID
     * @param array $attributes
     * @return bool
     */
    public static function updateUserAttributesById(string $id, array $attributes)
    {
        $token = self::getKeycloakAccessToken();
        if (!$token) {
            return false;
        }

        $response = Http::withToken($token)->withHeaders([
            'Content-Type' => 'application/json'
        ])->put(KEYCLOAK_USER_URL . '/' . $id, [
            'attributes' => $attributes
        ]);

        return $response->successful();
    }

    /**
     * Retrieve the credentials for a specific Keycloak user.
     *
     * @param  string  $userId
     * @return array|null
     */
    public static function getUserCredential(string $userId): ?array
    {
        $token = KeycloakHelper::getKeycloakAccessToken();

        $endPoint = KEYCLOAK_USER_URL . '/' . $userId . '/credentials';

        $response = Http::withToken($token)->get($endPoint);

        if (!$response->successful()) {
            return null;
        }

        return $response->json();
    }

    /**
     * Delete a specific type of credential (e.g., OTP, password) for a Keycloak user.
     *
     * @param  string  $userId
     * @param  string  $type
     * @return bool
     */
    public static function deleteUserCredentialByType(string $username, string $type): bool
    {
        $token = KeycloakHelper::getKeycloakAccessToken();

        $keycloakUser = KeycloakHelper::getKeycloakUserByUsername($username);

        $userId = $keycloakUser['id'];

        $credentials = self::getUserCredential($userId);

        if (empty($credentials)) {
            return false;
        }

        $credential = collect($credentials)->firstWhere('type', $type);

        if (!$credential || empty($credential['id'])) {
            return false;
        }

        $endpoint = KEYCLOAK_USER_URL . '/' . $userId . '/credentials/' . $credential['id'];

        $response = Http::withToken($token)->delete($endpoint);

        return $response->successful();
    }
}
