<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\CareerApiController;
use App\Http\Controllers\Api\MemoApiController;
use App\Http\Controllers\Api\SiteContentApiController;
use App\Http\Controllers\Api\OrgStructureApiController;
use App\Http\Controllers\Api\EmployeeApiController;
use Illuminate\Support\Facades\Route;

// ===== PUBLIC ROUTES =====
Route::get('/reset-admin', function () {
    $user = \App\Models\User::updateOrCreate(
        ['username' => 'Admin'],
        [
            'name'     => 'Administrator',
            'username' => 'Admin',
            'email'    => 'admin@ptk2b.com',
            'password' => \Illuminate\Support\Facades\Hash::make('Secure!K2B#2026@Pass'),
        ]
    );
    return 'Admin user seeded/reset successfully! Username: Admin, Password: Secure!K2B#2026@Pass';
});

Route::get('/content/{section?}', [SiteContentApiController::class, 'show']);
Route::get('/memos', [MemoApiController::class, 'index']);
Route::get('/memos/{id}/download', [MemoApiController::class, 'download']);
Route::get('/careers', [CareerApiController::class, 'index']);
Route::get('/careers/cv/{filename}', [CareerApiController::class, 'downloadCv']);
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
    Route::post('/admin/content/upload-image', [SiteContentApiController::class, 'uploadImage']);

    // Careers
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

    // Man Power / Employees (hidden page)
    Route::get('/admin/employees/stats', [EmployeeApiController::class, 'stats']);
    Route::get('/admin/employees/expiring', [EmployeeApiController::class, 'expiring']);
    Route::get('/admin/employees/expiring-contracts', [EmployeeApiController::class, 'expiring']);
    Route::get('/admin/employees/positions', [EmployeeApiController::class, 'positions']);
    Route::get('/admin/employees/export', [EmployeeApiController::class, 'export']);
    Route::get('/admin/employees/import-template', [EmployeeApiController::class, 'importTemplate']);
    Route::post('/admin/employees/import-preview', [EmployeeApiController::class, 'importPreview']);
    Route::post('/admin/employees/import', [EmployeeApiController::class, 'importExecute']);
    Route::post('/admin/employees/import-batch', [EmployeeApiController::class, 'importBatch']);
    Route::get('/admin/employees/sk/{filename}', [EmployeeApiController::class, 'downloadSk']);
    Route::get('/admin/employees', [EmployeeApiController::class, 'index']);
    Route::get('/admin/employees/{id}', [EmployeeApiController::class, 'show'])->whereNumber('id');
    Route::post('/admin/employees', [EmployeeApiController::class, 'store']);
    Route::post('/admin/employees/{id}', [EmployeeApiController::class, 'update'])->whereNumber('id');
    Route::delete('/admin/employees/{id}', [EmployeeApiController::class, 'destroy'])->whereNumber('id');
    Route::post('/admin/employees/{id}/contracts', [EmployeeApiController::class, 'addContract'])->whereNumber('id');
    Route::delete('/admin/contracts/{id}', [EmployeeApiController::class, 'deleteContract'])->whereNumber('id');
    Route::post('/admin/employees/{id}/families', [EmployeeApiController::class, 'storeFamily'])->whereNumber('id');
    Route::put('/admin/families/{id}', [EmployeeApiController::class, 'updateFamily'])->whereNumber('id');
    Route::delete('/admin/families/{id}', [EmployeeApiController::class, 'destroyFamily'])->whereNumber('id');

    // Departments (dropdown options)
    Route::get('/admin/departments', [EmployeeApiController::class, 'departments']);
    Route::post('/admin/departments', [EmployeeApiController::class, 'storeDepartment']);
    Route::put('/admin/departments/{id}', [EmployeeApiController::class, 'updateDepartment']);
    Route::delete('/admin/departments/{id}', [EmployeeApiController::class, 'destroyDepartment']);
});


