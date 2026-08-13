<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\CareerApiController;
use App\Http\Controllers\Api\MemoApiController;
use App\Http\Controllers\Api\SiteContentApiController;
use App\Http\Controllers\Api\OrgStructureApiController;
use Illuminate\Support\Facades\Route;

// ===== PUBLIC ROUTES =====
Route::get('/content/{section?}', [SiteContentApiController::class, 'show']);
Route::get('/memos', [MemoApiController::class, 'index']);
Route::get('/memos/{id}/download', [MemoApiController::class, 'download']);
Route::get('/careers', [CareerApiController::class, 'index']);
Route::get('/careers/{id}', [CareerApiController::class, 'show']);
Route::get('/structure', [OrgStructureApiController::class, 'index']);

// ===== RATE-LIMITED PUBLIC FORM ENDPOINTS =====
Route::middleware('throttle:10,1')->group(function () {
    Route::post('/career-apply', [CareerApiController::class, 'apply']);
    Route::post('/contact-message', [SiteContentApiController::class, 'sendContactMessage']);
    Route::post('/login', [AuthController::class, 'login']);
});


// ===== PROTECTED ROUTES (admin only) =====

Route::middleware('auth:sanctum')->group(function () {
    Route::get('/me', [AuthController::class, 'me']);
    Route::post('/logout', [AuthController::class, 'logout']);

    // Memos
    Route::post('/memos', [MemoApiController::class, 'store']);
    Route::delete('/memos/{id}', [MemoApiController::class, 'destroy']);

    // Site Content CMS
    Route::put('/content/{section}', [SiteContentApiController::class, 'update']);

    // Careers & CV Download Protection
    Route::get('/careers/cv/{filename}', [CareerApiController::class, 'downloadCv']);
    Route::post('/careers', [CareerApiController::class, 'store']);
    Route::put('/careers/{id}', [CareerApiController::class, 'update']);
    Route::delete('/careers/{id}', [CareerApiController::class, 'destroy']);

    // Org Structure CMS
    Route::get('/admin/structure', [OrgStructureApiController::class, 'adminIndex']);
    Route::post('/admin/structure', [OrgStructureApiController::class, 'store']);
    Route::post('/admin/structure/{id}', [OrgStructureApiController::class, 'update']);
    Route::put('/admin/structure/{id}', [OrgStructureApiController::class, 'update']);
    Route::delete('/admin/structure/{id}', [OrgStructureApiController::class, 'destroy']);

    // Contact Messages Inbox
    Route::get('/admin/messages', [SiteContentApiController::class, 'getMessages']);
    Route::delete('/admin/messages/{id}', [SiteContentApiController::class, 'destroyMessage']);

    // Career Applications Inbox
    Route::get('/admin/applications', [CareerApiController::class, 'getApplications']);
    Route::delete('/admin/applications/{id}', [CareerApiController::class, 'destroyApplication']);
});


