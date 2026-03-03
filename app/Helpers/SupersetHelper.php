<?php

namespace App\Helpers;

use Exception;
use Illuminate\Support\Facades\Http;
use Firebase\JWT\JWT;

class SupersetHelper
{
    public static function getBaseUrl(): string
    {
        return env('SUPERSET_BASE_URL');
    }

    public static function buildRlsClauses(array $replacementsMap, array $rlsConfigs): array
    {
        return collect($rlsConfigs)
            ->map(function ($r) use ($replacementsMap) {
                $clause = $r['clause'] ?? '';

                foreach ($replacementsMap as $placeholder => $value) {
                    $clause = str_replace($placeholder, $value, $clause);
                }

                $result = ['clause' => $clause];

                if (!empty($r['dataset'])) {
                    $result['dataset'] = $r['dataset'];
                }

                return $result;
            })
            ->values()
            ->toArray();
    }

    public static function generateGuestToken($guestTokenPayload): string
    {
        $accessToken = self::getAccessToken();
        $csrfData = self::getCsrfTokenAndCookies($accessToken);
        $csrfToken = $csrfData['csrf_token'];
        $cookies = $csrfData['cookies'];
        $supersetUrl = self::getBaseUrl();

        $guestResponse = Http::withHeaders([
            'X-CSRFToken' => $csrfToken,
            'Accept' => 'application/json',
            'Content-Type' => 'application/json',
        ])
            ->withToken($accessToken)
            ->withCookies($cookies, parse_url($supersetUrl, PHP_URL_HOST)) // Attach session cookies.
            ->post("$supersetUrl/api/v1/security/guest_token", $guestTokenPayload);

        if (!$guestResponse->successful()) {
            throw new Exception("Failed to get guest token: " . $guestResponse->body());
        }

        return $guestResponse->json()['token'];
    }

    public static function getExpirationTime(string $guestToken): ?int
    {
        $tokenParts = explode('.', $guestToken);

        if (count($tokenParts) !== 3) {
            throw new Exception("Invalid JWT token format");
        }

        $payloadJson = JWT::urlsafeB64Decode($tokenParts[1]);

        $payloadData = json_decode($payloadJson, true, 512, JSON_THROW_ON_ERROR);

        return $payloadData['exp'] ?? null;
    }

    private static function getAccessToken()
    {
        $supersetUrl = self::getBaseUrl();
        $supersetAdmin = env('SUPERSET_ADMIN_USER');
        $supersetPassword = env('SUPERSET_ADMIN_PASSWORD');

        $response = Http::post("$supersetUrl/api/v1/security/login", [
            'username' => $supersetAdmin,
            'password' => $supersetPassword,
            'provider' => 'db',
            'refresh' => true,
        ]);

        if (!$response->successful()) {
            throw new Exception("Failed to get access token: " . $response->body());
        }

        return $response->json()['access_token'];
    }

    private static function getCsrfTokenAndCookies($accessToken)
    {
        $supersetUrl = self::getBaseUrl();

        $response = Http::withToken($accessToken)
            ->withOptions(['verify' => false]) // Ignore SSL verification if needed.
            ->get("$supersetUrl/api/v1/security/csrf_token");

        if (!$response->successful()) {
            throw new Exception("Failed to get CSRF token: " . $response->body());
        }

        $csrfToken = $response->json()['result'];
        $cookies = [];

        foreach ($response->cookies() as $cookie) {
            $cookies[$cookie->getName()] = $cookie->getValue();
        }

        return [
            'csrf_token' => $csrfToken,
            'cookies' => $cookies,
        ];
    }
}
