<?php

namespace App\Http\Controllers;

use App\Http\Resources\ColorSchemeResource;
use App\Models\ColorScheme;
use Illuminate\Http\Request;

class ColorSchemeController extends Controller
{
    /**
     * @return array
     */
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

    /**
     * @param \Illuminate\Http\Request $request
     *
     * @return array
     */
    public function store(Request $request)
    {
        $primaryColor = $request->get('primary_color');
        $secondaryColor = $request->get('secondary_color');
        $data = ColorScheme::first();

        if ($data) {
            $update = [
                'primary_color' => $primaryColor,
                'secondary_color' => $secondaryColor,
            ];
            $data->update($update);
        } else {
            ColorScheme::create([
                'primary_color' => $primaryColor,
                'secondary_color' => $secondaryColor,
            ]);
        }
        return ['success' => true, 'message' => 'success_message.color_scheme.save'];
    }
}
