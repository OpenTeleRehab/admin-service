<?php

namespace App\Http\Middleware;

use App\Models\ApiClient;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Symfony\Component\HttpFoundation\Response;

class CheckApiClientAccess
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $apiKey = $request->header('X-API-KEY');
        $secretKey = $request->header('X-SECRET-KEY');

        if (!$apiKey || !$secretKey) {
            return response()->json(['message' => 'API key or secret missing'], 401);
        }

        $client = ApiClient::where('api_key', $apiKey)->where('active', true)->firstOrFail();

        if (!Hash::check($secretKey, $client->secret_key)) {
            return response()->json(['message' => 'Invalid secret key'], 403);
        }

        if (!empty($client->allow_ips) && is_array($client->allow_ips)) {
            $requestIp = $request->ip();
            if (!in_array($requestIp, $client->allow_ips)) {
                return response()->json(['message' => 'IP not allowed'], 403);
            }
        }

        return $next($request);
    }
}
