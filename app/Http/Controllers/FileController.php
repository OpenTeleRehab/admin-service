<?php

namespace App\Http\Controllers;

use App\Helpers\FileHelper;
use App\Models\File;
use App\Models\Forwarder;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Http;

class FileController extends Controller
{
    /**
     * @param \Illuminate\Http\Request $request
     * @param integer $id
     *
     * @return \Symfony\Component\HttpFoundation\BinaryFileResponse
     */
    public function show(Request $request, $id)
    {
        $file = File::find($id);

        if ($request->boolean('thumbnail')) {
            return response()->file(storage_path('app/' . $file->thumbnail));
        }

        return response()->file(storage_path('app/' . $file->path));
    }

    /**
     * @param \Illuminate\Http\Request $request
     *
     * @return array
     */
    public function uploadFile(Request $request)
    {
        if ($request->hasFile('file')) {
            if ($request->file('file')->isValid()) {
                $file = FileHelper::createFile($request->file('file'), File::FILE_PATH);

                return ['success' => true, 'message' => 'success_message.file_upload', 'data' => $file];
            }
        }

        return ['success' => false, 'message' => 'error_message.file_upload'];
    }

    /**
     * @param Request $request
     * @return \Symfony\Component\HttpFoundation\BinaryFileResponse|null
     */
    public function download(Request $request)
    {
        $user = Auth::user();
        if ($user->type === User::ADMIN_GROUP_GLOBAL_ADMIN ||
            $user->type === User::ADMIN_GROUP_ORG_ADMIN ||
            $user->type === User::ADMIN_GROUP_SUPER_ADMIN) {
            $filePath = storage_path($request->get('path'));
            if (file_exists($filePath) && is_file($filePath)) {
                return response()->download($filePath);
            }
            return null;
        } else {
            $country = $request->header('country');
            $endpoint = str_replace('api/', '/', $request->path());
            $access_token = Forwarder::getAccessToken(Forwarder::PATIENT_SERVICE, $country);
            $response = Http::withToken($access_token)->withHeaders([
                'country' => $country
            ])->get(env('PATIENT_SERVICE_URL') . $endpoint, $request->all());
            return response($response->body(), $response->status())
                ->withHeaders([
                    'Content-Type' => $response->header('Content-Type'),
                    'Content-Disposition' => $response->header('Content-Disposition'),
                ]);
        }
    }
}
