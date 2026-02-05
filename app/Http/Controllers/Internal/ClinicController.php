<?php

namespace App\Http\Controllers\Internal;

use App\Http\Controllers\Controller;
use App\Models\Clinic;
use Illuminate\Http\Request;

class ClinicController extends Controller
{
    
    /**
     * Get clinics by ids.
     *
     * @param \Illuminate\Http\Request $request
     *
     * @return array
     */
    public function getByIds(Request $request)
    {
        $clinicIds = $request->get('ids', []);
        $clinics = Clinic::whereIn('id', $clinicIds)->select('id', 'name')->get();
        return ['success' => true, 'data' => $clinics];
    }
}
