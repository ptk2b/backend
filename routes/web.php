<?php

use App\Http\Controllers\Api\CareerApiController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return response()->json([
        'status'    => 'ok',
        'service'   => 'PT K2B API Server',
        'timestamp' => now()->toISOString(),
    ]);
});

// Direct Web Route for CV Download
Route::get('/careers/cv/{filename}', [CareerApiController::class, 'downloadCv']);
