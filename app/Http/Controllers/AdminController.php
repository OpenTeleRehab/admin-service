<?php

namespace App\Http\Controllers;

use App\Helpers\KeycloakHelper;
use App\Http\Resources\UserResource;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;

define("KEYCLOAK_USERS", env('KEYCLOAK_URL') . '/auth/admin/realms/hi/users');

class AdminController extends Controller
{
    /**
     * @param \Illuminate\Http\Request $request
     *
     * @return array
     */
    public function index(Request $request)
    {
        $type = $request->get('admin_type');
        $users = User::where('type', $type)->get();

        return ['success' => true, 'data' => UserResource::collection($users)];
    }

    public function store(Request $request)
    {
        DB::beginTransaction();
        $firstName = $request->get('first_name');
        $lastName = $request->get('last_name');
        $type = $request->get('type');
        $countryId = $request->get('country_id');
        $hospitalId = $request->get('hospital_id');
        $email = $request->get('email');

        $availableEmail = User::where('email', $email)->count();
        if ($availableEmail) {
            //Todo: message will be replaced
            return abort(409, 'error_message.email_exists');
        }
        try {
            $user = User::create([
                'email' => $email,
                'first_name' => $firstName,
                'last_name' => $lastName,
                'type' => $type,
                'country_id' => $countryId,
                'hospital_id' => $hospitalId,
            ]);

            //create keycloak user
            $keycloakUserUuid = self::createKeycloakUser($user, $email, true, $type);

            if (!$user || !$keycloakUserUuid) {
                DB::rollBack();
                return abort(500);
            }
        } catch (\Exception $e) {
            DB::rollBack();
            return ['success' => false, 'message' => $e->getMessage()];
        }

        DB::commit();
        return ['success' => true, 'message' => 'success_message.user_add'];
    }

    /**
     * @param \Illuminate\Http\Request $request
     * @param $id
     *
     * @return array
     */
    public function update(Request $request, $id)
    {
        try {
            $user = User::findOrFail($id);
            $data = $request->all();
            $user->update([
                'first_name' => $data['first_name'],
                'last_name' => $data['last_name'],
            ]);
        } catch (\Exception $e) {
            return ['success' => false, 'message' => $e->getMessage()];
        }

        return ['success' => true, 'message' => 'success_message.user_update'];
    }

    private static function createKeycloakUser($user, $password, $isTemporaryPassword, $userGroup)
    {
        $token = KeycloakHelper::getKeycloakAccessToken();
        if ($token) {
            $response = Http::withToken($token)->withHeaders([
                'Content-Type' => 'application/json'
            ])->post(KEYCLOAK_USERS, [
                'username' => $user->email,
                'email' => $user->email,
                'enabled' => true,
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
                    return $userKeycloakUuid;
                }
            }
        }
        return false;
    }

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
