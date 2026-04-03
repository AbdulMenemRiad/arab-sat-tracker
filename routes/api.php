<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\SatelliteController;


Route::get('/telemetry', [SatelliteController::class, 'getTelemetry']);
