<?php

namespace App\Http\Controllers;

use App\Models\ApiClient;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class ApiClientController extends Controller
{
    public function index()
    {
        $apiClients = ApiClient::all();

        return response()->json(['data' => $apiClients]);
    }

    public function store(Request $request)
    {
        $validatedData = $request->validate([
            'name' => 'required|unique:api_clients,name',
            'allow_ips' => 'nullable|array',
            'allow_ips.*' => 'ip',
        ]);

        $apiKey = Str::random(32);
        $secretKey = Str::random(64);

        $validatedData['api_key'] = $apiKey;
        $validatedData['secret_key'] = Hash::make($secretKey);

        ApiClient::create($validatedData);

        return response()->json([
            'data' => [
                'api_key' => $apiKey,
                'secret_key' => $secretKey,
            ],
            'message' => 'api_client.create.success'
        ], 201);
    }

    public function update(Request $request, ApiClient $apiClient)
    {
        $validatedData = $request->validate([
            'name' => 'required|unique:api_clients,name,' . $apiClient->id,
            'allow_ips' => 'nullable|array',
            'allow_ips.*' => 'ip',
        ]);

        $apiClient->update($validatedData);

        return response()->json(['message' => 'api_client.update.success']);
    }

    public function destroy(ApiClient $apiClient)
    {
        $apiClient->delete();

        return response()->json(['message' => 'api_client.delete.success']);
    }

    public function updateStatus(ApiClient $apiClient)
    {
        $apiClient->update(['active' => !$apiClient->active]);

        return response()->json(['message' => 'api_client.update_status.success']);
    }

    public function regenerateSecretKey($apiKey)
    {
        $apiClient = ApiClient::where('api_key', $apiKey)->firstOrFail();

        $secretKey = Str::random(64);

        $apiClient->update(['secret_key' => Hash::make($secretKey)]);

        return response()->json([
            'data' => [
                'api_key' => $apiClient->api_key,
                'secret_key' => $secretKey,
            ],
            'message' => 'api_client.secret_key.generate.success'
        ]);
    }
}
