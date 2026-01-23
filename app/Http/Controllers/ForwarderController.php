<?php

namespace App\Http\Controllers;

use App\Models\Forwarder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Auth;

class ForwarderController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @param \Illuminate\Http\Request $request
     * @return mixed
     */
    public function index(Request $request)
    {
        $service_name = $request->route()->getName();
        $endpoint = str_replace('api/', '/', $request->path());
        $params = $request->all();
        $user = auth()->user();

        if ($service_name !== null && str_contains($service_name, Forwarder::GADMIN_SERVICE)) {
            $access_token = Forwarder::getAccessToken(Forwarder::GADMIN_SERVICE);
            return Http::withToken($access_token)->get(env('GLOBAL_ADMIN_SERVICE_URL') . $endpoint, $params);
        }

        if ($service_name !== null && str_contains($service_name, Forwarder::THERAPIST_SERVICE)) {
            $access_token = Forwarder::getAccessToken(Forwarder::THERAPIST_SERVICE);
            $params['country_id'] ??= $user->country_id;
            $params['clinic_id'] ??= $user->clinic_id;
            return Http::withToken($access_token)->get(env('THERAPIST_SERVICE_URL') . $endpoint, $params);
        }

        if ($service_name !== null && str_contains($service_name, Forwarder::PATIENT_SERVICE)) {
            $access_token = Forwarder::getAccessToken(Forwarder::PATIENT_SERVICE, $user->country?->iso_code);
            $response = Http::withToken($access_token)->withHeaders([
                'country' => $user->country?->iso_code,
                'int-clinic-id' => $user->clinic_id,
            ])->get(env('PATIENT_SERVICE_URL') . $endpoint, $params);
            return response($response->body(), $response->status())
                ->withHeaders([
                    'Content-Type' => $response->header('Content-Type'),
                    'Content-Disposition' => $response->header('Content-Disposition'),
                ]);
        }
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return mixed
     */
    public function store(Request $request)
    {
        $service_name = $request->route()->getName();
        $endpoint = str_replace('api/', '/', $request->path());
        $user = Auth::user();
        if ($service_name !== null && str_contains($service_name, Forwarder::THERAPIST_SERVICE)) {
            $access_token = Forwarder::getAccessToken(Forwarder::THERAPIST_SERVICE);
            return Http::withToken($access_token)->withHeaders([
                'Accept' => 'application/json',
                'int-country-id' => $user->country_id,
                'int-region-id' => $user?->region_id,
                'int-province-id' => $user?->clinic?->province_id ?: $user?->phcService?->province_id,
                'int-phc-service-id' => $user?->phc_service_id,
                'int-clinic-id' => $user->clinic_id,
                'int-user-type' => $user?->type,
                ])
                ->post(env('THERAPIST_SERVICE_URL') . $endpoint, $request->all());
        } elseif ($service_name !== null && str_contains($service_name, Forwarder::PATIENT_SERVICE)) {
            $access_token = Forwarder::getAccessToken(Forwarder::PATIENT_SERVICE, $user->country?->iso_code);
            $response = Http::withToken($access_token)->withHeaders([
                'country' => $user->country?->iso_code,
                'int-country-id' => $user->country_id,
                'int-region-id' => $user?->region_id,
                'int-province-id' => $user?->clinic?->province_id ?: $user?->phcService?->province_id,
                'int-phc-service-id' => $user?->phc_service_id,
                'int-clinic-id' => $user->clinic_id,
                'int-user-type' => $user?->type,
                ])
                ->post(env('PATIENT_SERVICE_URL') . $endpoint, $request->all());

            return response($response->body(), $response->status())
                ->withHeaders($response->headers());
        }

        abort('400');
    }

    /**
     * Display the specified resource.
     *
     * @param \Illuminate\Http\Request $request
     * @param int $id
     *
     * @return \GuzzleHttp\Promise\PromiseInterface|\Illuminate\Http\Client\Response|\Illuminate\Http\Response
     */
    public function show(Request $request)
    {
        $service_name = $request->route()->getName();
        $endpoint = str_replace('api/', '/', $request->path());
        $user = Auth::user();

        if ($service_name !== null && str_contains($service_name, Forwarder::PATIENT_SERVICE)) {
            $access_token = Forwarder::getAccessToken(Forwarder::PATIENT_SERVICE, $user->country?->iso_code);
            return Http::withToken($access_token)->withHeaders([
                'country' => $user->country?->iso_code,
                'int-clinic-id' => $user->clinic_id,
            ])->get(env('PATIENT_SERVICE_URL') . $endpoint, $request->all());
        }
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  int  $id
     * @return mixed
     */
    public function update(Request $request, $id)
    {
        $service_name = $request->route()->getName();
        $endpoint = str_replace('api/', '/', $request->path());
        $user = Auth::user();

        if ($service_name !== null && str_contains($service_name, Forwarder::THERAPIST_SERVICE)) {
            $access_token = Forwarder::getAccessToken(Forwarder::THERAPIST_SERVICE);
            $response = Http::withToken($access_token)
                ->put(env('THERAPIST_SERVICE_URL') . $endpoint, $request->all());
        }

        if ($service_name !== null && str_contains($service_name, Forwarder::PATIENT_SERVICE)) {
            $accessToken = Forwarder::getAccessToken(Forwarder::PATIENT_SERVICE);

            $response = Http::withToken($accessToken)
                ->withHeaders([
                    'Accept' => 'application/json',
                    'int-clinic-id' => $user->clinic_id,
                ])
                ->put(env('PATIENT_SERVICE_URL') . $endpoint, $request->all());
        }

        return $response;
    }
}
