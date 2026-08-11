<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\CareerApiController;
use App\Http\Controllers\Api\CpanelMailController;
use App\Http\Controllers\Api\MemoApiController;
use App\Http\Controllers\Api\SiteContentApiController;
use Illuminate\Support\Facades\Route;

// ===== PUBLIC ROUTES =====
Route::get('/content/{section?}', [SiteContentApiController::class, 'show']);
Route::get('/memos', [MemoApiController::class, 'index']);
Route::get('/memos/{id}/download', [MemoApiController::class, 'download']);
Route::get('/careers', [CareerApiController::class, 'index']);
Route::get('/careers/{id}', [CareerApiController::class, 'show']);
Route::post('/career-apply', [CareerApiController::class, 'apply']);
Route::post('/contact-message', [SiteContentApiController::class, 'sendContactMessage']);

// ===== AUTH =====
Route::post('/login', [AuthController::class, 'login']);

// ===== WEBMAIL (cPanel Email Auth & Sender) =====
Route::prefix('webmail')->group(function () {
    Route::post('/login', [CpanelMailController::class, 'login']);
    Route::get('/me', [CpanelMailController::class, 'me']);
    Route::post('/send', [CpanelMailController::class, 'sendMail']);
    Route::post('/logout', [CpanelMailController::class, 'logout']);
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

    // Careers
    Route::post('/careers', [CareerApiController::class, 'store']);
    Route::put('/careers/{id}', [CareerApiController::class, 'update']);
    Route::delete('/careers/{id}', [CareerApiController::class, 'destroy']);

    // Contact Messages Inbox
    Route::get('/admin/messages', [SiteContentApiController::class, 'getMessages']);
    Route::delete('/admin/messages/{id}', [SiteContentApiController::class, 'destroyMessage']);

    // Career Applications Inbox
    Route::get('/admin/applications', [CareerApiController::class, 'getApplications']);
    Route::delete('/admin/applications/{id}', [CareerApiController::class, 'destroyApplication']);
});
