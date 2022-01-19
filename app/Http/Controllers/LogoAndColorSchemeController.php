<?php

namespace App\Http\Controllers;

use App\Helpers\FileHelper;
use App\Http\Resources\LogoAndColorSchemeResource;
use App\Models\LogoAndColorScheme;
use Illuminate\Http\Request;
use App\Models\File;
use Illuminate\Support\Facades\Log;

class LogoAndColorSchemeController extends Controller
{
    public function index()
    {
        $data = LogoAndColorScheme::first();
        return ['success' => true, 'data' => $data ? new LogoAndColorSchemeResource($data) : []];
    }

    /**
     * @param \App\Models\LogoAndColorScheme $logoAndColorScheme
     *
     * @return \App\Http\Resources\LogoAndColorSchemeResource
     */
    public function show(LogoAndColorScheme $logoAndColorScheme)
    {
        return new LogoAndColorSchemeResource($logoAndColorScheme);
    }

    public function store(Request $request)
    {
        $wepLogoFile = $request->file('web_logo');
        $mobileLogoFile = $request->file('mobile_logo');
        $faviconFile = $request->file('favicon');
        $color = $request->get('color');
        $newWebLogoFile = $wepLogoFile ? FileHelper::createFile($wepLogoFile, File::ORG_LOGO_PATH) : null;;
        $newMobileLogoFile = $mobileLogoFile ? FileHelper::createFile($mobileLogoFile, File::ORG_LOGO_PATH) : null;
        $newFaviconFile = $faviconFile ? FileHelper::createFile($faviconFile, File::ORG_LOGO_PATH) : null;
        $data = LogoAndColorScheme::first();

        if ($data) {
            if ($newWebLogoFile) {
                $removeFile = File::find($data->web_logo);
                $removeFile->delete();
            }
            if($newMobileLogoFile) {
                $removeFile = File::find($data->mobile_logo);
                $removeFile->delete();
            }
            if($newFaviconFile) {
                $removeFile = File::find($data->favicon);
                $removeFile->delete();
            }
            $update = [
                'web_logo' => $newWebLogoFile ? $newWebLogoFile->id : $data->web_logo,
                'mobile_logo' => $newMobileLogoFile ? $newMobileLogoFile->id : $data->mobile_logo,
                'favicon' => $newFaviconFile ? $newFaviconFile->id : $data->favicon,
                'color' => $color
            ];
            $data->update($update);
        } else {
            LogoAndColorScheme::create([
                'web_logo' => $newWebLogoFile ? $newWebLogoFile->id : null,
                'mobile_logo' => $newMobileLogoFile ? $newMobileLogoFile->id : null,
                'favicon' => $newFaviconFile ? $newFaviconFile->id : null,
                'color' => $color,
            ]);
        }
        return ['success' => true, 'message' => 'success_message.logo_and_color.save'];
    }
}
