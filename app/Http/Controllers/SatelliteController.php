<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class SatelliteController extends Controller
{
    public function getTelemetry()
    {
        // Check if the file exists in storage/app
        if (!Storage::exists('regional_satellites.json')) {
            return response()->json(['error' => 'Telemetry data not found.'], 404);
        }

        // Read the file
        $json = Storage::get('regional_satellites.json');

        // Decode it to an array, then send it back as a proper JSON response
        $data = json_decode($json, true);

        return response()->json([
            'status' => 'success',
            'timestamp' => now()->toIso8601String(),
            'data' => $data
        ]);
    }
}
