<?php

namespace App\Http\Controllers;

use App\Http\Resources\ColorSchemeResource;
use App\Models\ColorScheme;
use Illuminate\Http\Request;

class ColorSchemeController extends Controller
{
    public function index()
    {
        $data = ColorScheme::first();
        return ['success' => true, 'data' => $data ? new ColorSchemeResource($data) : []];
    }

    /**
     * @param \App\Models\ColorScheme $colorScheme
     *
     * @return \App\Http\Resources\ColorSchemeResource
     */
    public function show(ColorScheme $colorScheme)
    {
        return new ColorSchemeResource($colorScheme);
    }

    public function store(Request $request)
    {
        $primaryColor = $request->get('primary_color');
        $secondaryColor = $request->get('secondary_color');
        $primaryTextColor = $request->get('primary_text_color');
        $secondaryTextColor = $request->get('secondary_text_color');
        $data = ColorScheme::first();

        if ($data) {
            $update = [
                'primary_color' => $primaryColor,
                'secondary_color' => $secondaryColor,
                'primary_text_color' => $primaryTextColor,
                'secondary_text_color' => $secondaryTextColor,
            ];
            $data->update($update);
        } else {
            ColorScheme::create([
                'primary_color' => $primaryColor,
                'secondary_color' => $secondaryColor,
                'primary_text_color' => $primaryTextColor,
                'secondary_text_color' => $secondaryTextColor,
            ]);
        }
        return ['success' => true, 'message' => 'success_message.color_scheme.save'];
    }
}
