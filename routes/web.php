<?php

use App\Http\Controllers\Api\CareerApiController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

// Fallback & Direct Web Routes for CV Download
Route::get('/careers/cv/{filename}', [CareerApiController::class, 'downloadCv']);
Route::get('/api/careers/cv/{filename}', [CareerApiController::class, 'downloadCv']);
Route::get('/uploads/cvs/{filename}', [CareerApiController::class, 'downloadCv']);
Route::get('/storage/uploads/cvs/{filename}', [CareerApiController::class, 'downloadCv']);

Route::get('/run-migrations', function () {
    try {
        \Illuminate\Support\Facades\Artisan::call('migrate', ['--force' => true]);
        return response()->json([
            'status' => 'success',
            'message' => 'Migrations executed successfully!',
            'output' => \Illuminate\Support\Facades\Artisan::output()
        ]);
    } catch (\Exception $e) {
        return response()->json([
            'status' => 'error',
            'message' => $e->getMessage()
        ], 500);
    }
});

