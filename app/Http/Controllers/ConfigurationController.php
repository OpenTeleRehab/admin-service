<?php

namespace App\Http\Controllers;

use App\Models\Configuration;
use Illuminate\Http\Request;

class ConfigurationController extends Controller
{
    public function index(Request $request)
    {
        $validatedData = $request->validate([
            'name' => 'required|string',
        ]);

        $config = Configuration::where('name', $validatedData['name'])->firstOrFail();

        return response()->json([
            'data' => $config,
        ]);
    }

    public function store(Request $request)
    {
        $validatedData = $request->validate([
            'name' => 'required|string|unique:configurations,name',
            'config' => 'required|array',
            'config.*.role' => 'required|string',
            'config.*.dashboard_id' => 'required|string',
            'config.*.rls' => 'nullable|array',
            'config.*.rls.*.clause' => 'required|string',
        ]);

        Configuration::updateOrCreate(
            ['name' => $validatedData['name']],
            ['config' => $validatedData['config']]
        );

        return response()->json(['message' => 'Configuration saved successfully.',]);
    }
}
