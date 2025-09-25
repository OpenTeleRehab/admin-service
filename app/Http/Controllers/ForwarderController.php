<?php

namespace App\Http\Controllers;

use App\Models\Forwarder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;

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
        $country = $request->header('country');
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
            $access_token = Forwarder::getAccessToken(Forwarder::PATIENT_SERVICE, $country);
            $response = Http::withToken($access_token)->withHeaders([
                'country' => $country
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

        if ($service_name !== null && str_contains($service_name, Forwarder::THERAPIST_SERVICE)) {
            $access_token = Forwarder::getAccessToken(Forwarder::THERAPIST_SERVICE);
            return Http::withToken($access_token)
                ->post(env('THERAPIST_SERVICE_URL') . $endpoint, $request->all());
        } elseif ($service_name !== null && str_contains($service_name, Forwarder::PATIENT_SERVICE)) {
            $access_token = Forwarder::getAccessToken(Forwarder::PATIENT_SERVICE);
            return Http::withToken($access_token)
                ->post(env('PATIENT_SERVICE_URL') . $endpoint, $request->all());
        }

        abort('400');
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

        if ($service_name !== null && str_contains($service_name, Forwarder::THERAPIST_SERVICE)) {
            $access_token = Forwarder::getAccessToken(Forwarder::THERAPIST_SERVICE);
            $response = Http::withToken($access_token)
                ->put(env('THERAPIST_SERVICE_URL') . $endpoint, $request->all());
        }

        return $response;
    }
}
