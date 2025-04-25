<?php

namespace App\Http\Controllers;

use App\Jobs\GenerateExport;
use App\Models\DownloadTracker;
use App\Models\Forwarder;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Http;

class ExportController extends Controller
{
    const TYPE_PATIENT_RAW_DATA = 'patient_raw_data';
    const TYPE_SURVEY_RESULT = 'survey_result';

    public function export(Request $request)
    {
        //TODO: Should improve this to be more generic.
        $user = Auth::user();
        $userId = $user->id;
        $jobId = $userId . now();
        $lang = $request->get('lang', 'en');
        $type = $request->get('type');
        $country = $request->header('country');
        $payload = [
            'job_id' => $jobId,
            'lang' => $lang,
            'type' => $type,
        ];

        if ($type === self::TYPE_PATIENT_RAW_DATA) {
            $payload['search_value'] = $request->get('search_value');
            $payload['filters'] = $request->get('filters');
            $payload['user_type'] = $user->type;
            $payload['country'] = $user->country_id;
            $payload['clinic'] = $user->clinic_id;
            GenerateExport::dispatch($payload);
            $canSave = true;
        } else if ($type === self::TYPE_SURVEY_RESULT) {
            $payload['survey_id'] = $request->integer('id');
            GenerateExport::dispatch($payload);
            $canSave = true;
        } else {
            if ($user->type === User::ADMIN_GROUP_GLOBAL_ADMIN ||
                $user->type === User::ADMIN_GROUP_ORG_ADMIN ||
                $user->type === User::ADMIN_GROUP_SUPER_ADMIN
            ) {
                GenerateExport::dispatch($payload);
                $canSave = true;
            } else {
                if ($user->type === User::ADMIN_GROUP_CLINIC_ADMIN) {
                    $payload['clinic_admin_id'] = $user->id;
                    $payload['clinic_id'] = $user->clinic_id;
                } else if ($user->type === User::ADMIN_GROUP_COUNTRY_ADMIN) {
                    $payload['country_admin_id'] = $user->id;
                }

                $payload['source'] = Forwarder::GADMIN_SERVICE;
                $access_token = Forwarder::getAccessToken(Forwarder::PATIENT_SERVICE, $country);
                $response = Http::withToken($access_token)->withHeaders([
                    'country' => $country
                ])->get(env('PATIENT_SERVICE_URL') . '/export', $payload);
                $canSave = $response->ok();
            }
        }

        if ($canSave) {
            DownloadTracker::create([
                'type' => $type,
                'job_id' => $jobId,
                'author_id' => $userId,
            ]);
            return ['success' => true, 'message' => 'success_message.export', 'data' => $jobId];
        } else {
            return ['success' => false, 'message' => 'error_message.export'];
        }
    }

}
