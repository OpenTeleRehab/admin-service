<?php

namespace App\Http\Controllers;

use App\Helpers\KeycloakHelper;
use App\Http\Resources\UserResource;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Http;

class ProfileController extends Controller
{
    /**
     * @OA\Get(
     *     path="/api/user/profile",
     *     tags={"User profile"},
     *     summary="Get user profile",
     *     operationId="getUserProfile",
     *     @OA\Response(
     *         response="200",
     *         description="successful operation"
     *     ),
     *     @OA\Response(response=400, description="Bad request"),
     *     @OA\Response(response=404, description="Resource Not Found"),
     *     @OA\Response(response=401, description="Authentication is required"),
     *     security={
     *         {
     *             "oauth2_security": {}
     *         }
     *     },
     * )
     *
     * @return \App\Http\Resources\UserResource
     */
    public function getUserProfile()
    {
        $user = Auth::user();
        // Update enabled to true when first login.
        if (!$user->last_login) {
            $user->update([
                'last_login' => now(),
                'enabled' => true
            ]);
        }

        return new UserResource($user);
    }

    /**
     * @OA\Put(
     *     path="/api/user/update-password",
     *     tags={"User profile"},
     *     summary="Update user password",
     *     operationId="updateUserPassword",
     *     @OA\Parameter(
     *         name="current_password",
     *         in="query",
     *         description="Current password",
     *         required=true,
     *         @OA\Schema(
     *             type="string"
     *         )
     *     ),
     *     @OA\Parameter(
     *         name="new_password",
     *         in="query",
     *         description="New password",
     *         required=true,
     *         @OA\Schema(
     *             type="string"
     *         )
     *     ),
     *     @OA\Response(
     *         response="200",
     *         description="successful operation"
     *     ),
     *     @OA\Response(response=400, description="Bad request"),
     *     @OA\Response(response=404, description="Resource Not Found"),
     *     @OA\Response(response=401, description="Authentication is required"),
     *     security={
     *         {
     *             "oauth2_security": {}
     *         }
     *     },
     * )
     *
     * @param \Illuminate\Http\Request $request
     *
     * @return array|bool[]
     */
    public function updatePassword(Request $request)
    {
        $user = Auth::user();
        $password = $request->get('current_password');
        $userResponse = KeycloakHelper::getLoginUser($user->email, $password);
        if ($userResponse->successful()) {
            // TODO: use own user token.
            $token = KeycloakHelper::getKeycloakAccessToken();
            $userUrl = KeycloakHelper::getUserUrl() . '/' .  KeycloakHelper::getUserUuid();
            $newPassword = $request->get('new_password');
            $isCanSetPassword = KeycloakHelper::resetUserPassword(
                $token,
                $userUrl,
                $newPassword,
                false
            );

            if ($isCanSetPassword) {
                return ['success' => true];
            }

            return ['success' => false, 'message' => 'error_message.cannot_change_password'];
        }

        return ['success' => false, 'message' => 'error_message.wrong_password'];
    }

    /**
     * @OA\Put(
     *     path="/api/user/update-information",
     *     tags={"User profile"},
     *     summary="Update user profile",
     *     operationId="updateUserProfile",
     *     @OA\Parameter(
     *         name="first_name",
     *         in="query",
     *         description="First name",
     *         required=true,
     *         @OA\Schema(
     *             type="string"
     *         )
     *     ),
     *     @OA\Parameter(
     *         name="last_name",
     *         in="query",
     *         description="Last name",
     *         required=true,
     *         @OA\Schema(
     *             type="string"
     *         )
     *     ),
     *     @OA\Parameter(
     *         name="gender",
     *         in="query",
     *         description="gender",
     *         required=true,
     *         @OA\Schema(
     *             type="string",
     *             enum={"male", "female", "other"}
     *         )
     *     ),
     *     @OA\Parameter(
     *         name="language_id",
     *         in="query",
     *         description="Language id",
     *         required=true,
     *         @OA\Schema(
     *             type="integer"
     *         )
     *     ),
     *     @OA\Parameter(
     *         name="language_code",
     *         in="query",
     *         description="Language code",
     *         required=true,
     *         @OA\Schema(
     *             type="string"
     *         )
     *     ),
     *     @OA\Response(
     *         response="200",
     *         description="successful operation"
     *     ),
     *     @OA\Response(response=400, description="Bad request"),
     *     @OA\Response(response=404, description="Resource Not Found"),
     *     @OA\Response(response=401, description="Authentication is required"),
     *     security={
     *         {
     *             "oauth2_security": {}
     *         }
     *     },
     * )
     *
     * @param \Illuminate\Http\Request $request
     *
     * @return array
     */
    public function updateUserProfile(Request $request)
    {
        try {
            $user = Auth::user();
            $data = $request->all();
            $user->update([
                'first_name' => $data['first_name'],
                'last_name' => $data['last_name'],
                'gender' => $data['gender'],
                'language_id' => $data['language_id'],
                'notifiable' => $data['notifiable'],
            ]);

            if ($data['language_code']) {
                try {
                    $this->updateUserLocale($user->email, $data['language_code']);
                } catch (\Exception $e) {
                    return ['success' => false, 'message' => $e->getMessage()];
                }
            }
        } catch (\Exception $e) {
            return ['success' => false, 'message' => $e->getMessage()];
        }

        return ['success' => true, 'message' => 'success_message.profile_update'];
    }

    /**
     * @OA\Put(
     *     path="/api/user/update-last-access",
     *     tags={"User profile"},
     *     summary="Update user last access",
     *     operationId="updateUserLastAccess",
     *     @OA\Response(
     *         response="200",
     *         description="successful operation"
     *     ),
     *     @OA\Response(response=400, description="Bad request"),
     *     @OA\Response(response=404, description="Resource Not Found"),
     *     @OA\Response(response=401, description="Authentication is required"),
     *     security={
     *         {
     *             "oauth2_security": {}
     *         }
     *     },
     * )
     *
     * @return array
     */
    public function updateLastAccess()
    {
        try {
            $user = Auth::user();
            $user->update([
                'last_login' => now(),
                'enabled' => true,
            ]);
            return ['success' => true, 'message' => 'Successful'];
        } catch (\Exception $e) {
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }

    /**
     * @param string $email
     * @param string $languageCode
     *
     * @return bool
     * @throws \Exception
     */
    private function updateUserLocale($email, $languageCode)
    {
        $token = KeycloakHelper::getKeycloakAccessToken();

        if ($token) {
            try {
                $userUrl = KeycloakHelper::getUserUrl() . '?email=' . $email;

                $response = Http::withToken($token)->get($userUrl);
                $keyCloakUsers = $response->json();
                $url = KeycloakHelper::getUserUrl() . '/' . $keyCloakUsers[0]['id'];
                $user = $keyCloakUsers[0];

                $data = [
                    'firstName' => $user['firstName'] ?? null,
                    'lastName'  => $user['lastName'] ?? null,
                    'email'     => $user['email'] ?? null,
                    'attributes' => array_merge($user['attributes'] ?? [], ['locale' => [$languageCode]])
                ];

                $response = Http::withToken($token)->put($url, $data);

                return $response->successful();
            } catch (\Exception $e) {
                throw new \Exception($e->getMessage());
            }
        }

        throw new \Exception('no_token');
    }
}
