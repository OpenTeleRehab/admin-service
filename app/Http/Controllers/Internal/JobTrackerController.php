<?php

namespace App\Http\Controllers\Internal;

use App\Http\Controllers\Controller;
use App\Models\JobTracker;
use Illuminate\Http\Request;

class JobTrackerController extends Controller
{
    public function update(Request $request, $id)
    {
        $validatedData = $request->validate([
            'status' => 'required|in:queued,running,completed,failed',
            'message' => 'nullable|string',
        ]);

        JobTracker::where('job_id', $id)->update($validatedData);

        return response()->json(['message' => 'Job tracker updated successfully']);
    }
}
