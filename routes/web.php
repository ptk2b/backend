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
