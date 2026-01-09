<?php

namespace App\Helpers;

use Illuminate\Support\Facades\Http;
use App\Models\Forwarder;
use Illuminate\Support\Facades\Log;

class GlobalDataSyncHelper
{
  /**
   * Make a request to Global Admin service.
   * Automatically uses internal access token or external secret key.
   *
   * @param string $endpoint API endpoint, e.g., 'get-exercises'
   * @param array $queryParams Optional query parameters
   * @return array|object|null JSON-decoded response
   */
  public static function fetchData(string $endpoint, array $queryParams = [])
  {
    $apiKey =  env('GLOBAL_ADMIN_SERVICE_API_KEY');
    $secretKey = env('GLOBAL_ADMIN_SERVICE_SECRET_KEY');
    if ($secretKey) {
      $response = Http::withHeaders([
          'X-API-KEY' => $apiKey,
          'X-SECRET-KEY' => $secretKey,
      ])->get(env('GLOBAL_ADMIN_SERVICE_URL') . '/external/' . $endpoint, $queryParams);
    } else {
      $accessToken = Forwarder::getAccessToken(Forwarder::GADMIN_SERVICE);
      $response = Http::withToken($accessToken)->get(env('GLOBAL_ADMIN_SERVICE_URL') . '/' . $endpoint, $queryParams);
    }

    if ($response->failed()) {
        Log::error("Failed to fetch data from Global Admin service: " . $response->body());
        return null;
    }
    return json_decode($response->body());
  }
}
