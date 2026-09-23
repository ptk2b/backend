<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\CareerApiController;
use App\Http\Controllers\Api\MemoApiController;
use App\Http\Controllers\Api\SiteContentApiController;
use App\Http\Controllers\Api\OrgStructureApiController;
use App\Http\Controllers\Api\EmployeeApiController;
use App\Http\Controllers\Api\EmployeeSanctionApiController;
use App\Http\Controllers\Api\AttendanceApiController;
use App\Http\Controllers\Api\UserApiController;
use Illuminate\Support\Facades\Route;

// ===== PUBLIC ROUTES =====
Route::get('/content/{section?}', [SiteContentApiController::class, 'show']);
Route::get('/memos', [MemoApiController::class, 'index']);
Route::get('/memos/{id}/download', [MemoApiController::class, 'download']);
Route::get('/careers', [CareerApiController::class, 'index']);
Route::get('/careers/cv/{filename}', [CareerApiController::class, 'downloadCv']);
Route::get('/careers/{id}', [CareerApiController::class, 'show']);
Route::get('/structure', [OrgStructureApiController::class, 'index']);
Route::get('/employees/{id}/sk', [EmployeeApiController::class, 'downloadEmployeeSk'])->whereNumber('id');
Route::get('/employees/sk/{filename}', [EmployeeApiController::class, 'downloadSk']);
Route::get('/contracts/{id}/sk', [EmployeeApiController::class, 'downloadContractSk'])->whereNumber('id');
Route::get('/admin/sanctions/{id}/download', [EmployeeSanctionApiController::class, 'downloadFile'])->whereNumber('id');

// Fallback login route for unauthenticated API requests
Route::get('/login', function () {
    return response()->json(['message' => 'Unauthenticated.'], 401);
})->name('login');

// ===== RATE-LIMITED PUBLIC FORM ENDPOINTS =====
Route::middleware('throttle:10,1')->group(function () {
    Route::post('/career-apply', [CareerApiController::class, 'apply']);
    Route::post('/contact-message', [SiteContentApiController::class, 'sendContactMessage']);
    Route::post('/login', [AuthController::class, 'login']);
});

// ===== PROTECTED ROUTES =====
Route::middleware('auth:sanctum')->group(function () {
    Route::get('/me', [AuthController::class, 'me']);
    Route::post('/logout', [AuthController::class, 'logout']);

    // Read-only Man Power / Employees & Departments (accessible by both Admin and Viewer)
    Route::get('/admin/employees/bootstrap', [EmployeeApiController::class, 'bootstrap']);
    Route::get('/admin/employees/stats', [EmployeeApiController::class, 'stats']);
    Route::get('/admin/employees/expiring', [EmployeeApiController::class, 'expiring']);
    Route::get('/admin/employees/positions', [EmployeeApiController::class, 'positions']);
    Route::get('/admin/employees/educations', [EmployeeApiController::class, 'educations']);
    Route::get('/admin/employees', [EmployeeApiController::class, 'index']);
    Route::get('/admin/employees/{id}', [EmployeeApiController::class, 'show'])->whereNumber('id');
    Route::get('/admin/departments', [EmployeeApiController::class, 'departments']);

    // Org Structure list & Inbox (read-only)
    Route::get('/admin/structure', [OrgStructureApiController::class, 'adminIndex']);
    Route::get('/admin/messages', [SiteContentApiController::class, 'getMessages']);
    Route::get('/admin/applications', [CareerApiController::class, 'getApplications']);

    // Sanksi & SP Karyawan (Read-only: accessible by Admin, HRD, Viewer)
    Route::get('/admin/sanctions/lookup-options', [EmployeeSanctionApiController::class, 'lookupOptions']);
    Route::get('/admin/sanctions/matrix', [EmployeeSanctionApiController::class, 'summaryMatrix']);
    Route::get('/admin/sanctions/employee/{employeeId}/active', [EmployeeSanctionApiController::class, 'activeWarningsByEmployee'])->whereNumber('employeeId');
    Route::get('/admin/sanctions', [EmployeeSanctionApiController::class, 'index']);
    Route::get('/admin/sanctions/{id}', [EmployeeSanctionApiController::class, 'show'])->whereNumber('id');

    // Sanksi & SP Karyawan Mutations (Accessible by Admin and HRD)
    Route::middleware('role.admin_or_hrd')->group(function () {
        Route::post('/admin/sanctions', [EmployeeSanctionApiController::class, 'store']);
        Route::match(['post', 'put'], '/admin/sanctions/{id}', [EmployeeSanctionApiController::class, 'update'])->whereNumber('id');
        Route::delete('/admin/sanctions/{id}', [EmployeeSanctionApiController::class, 'destroy'])->whereNumber('id');

        // Monitoring Absensi Karyawan (Admin & HRD)
        Route::get('/admin/attendance/matrix', [AttendanceApiController::class, 'matrix']);
        Route::post('/admin/attendance/update', [AttendanceApiController::class, 'update']);
        Route::post('/admin/attendance/seed-initial', [AttendanceApiController::class, 'seedInitial']);
    });

    // ===== MUTATING & ADMIN-ONLY ROUTES (Protected by role.admin) =====
    Route::middleware('role.admin')->group(function () {
        // User Management & Password Reset
        Route::get('/admin/users', [UserApiController::class, 'index']);
        Route::post('/admin/users', [UserApiController::class, 'store']);
        Route::put('/admin/users/{id}/reset-password', [UserApiController::class, 'resetPassword'])->whereNumber('id');
        Route::delete('/admin/users/{id}', [UserApiController::class, 'destroy'])->whereNumber('id');

        // Memos Mutation
        Route::post('/memos', [MemoApiController::class, 'store']);
        Route::delete('/memos/{id}', [MemoApiController::class, 'destroy']);

        // Site Content CMS
        Route::put('/content/{section}', [SiteContentApiController::class, 'update']);
        Route::post('/admin/content/upload-image', [SiteContentApiController::class, 'uploadImage']);

        // Careers Mutation
        Route::post('/careers', [CareerApiController::class, 'store']);
        Route::put('/careers/{id}', [CareerApiController::class, 'update']);
        Route::delete('/careers/{id}', [CareerApiController::class, 'destroy']);

        // Org Structure Mutation
        Route::post('/admin/structure', [OrgStructureApiController::class, 'store']);
        Route::match(['post', 'put'], '/admin/structure/{id}', [OrgStructureApiController::class, 'update']);
        Route::delete('/admin/structure/{id}', [OrgStructureApiController::class, 'destroy']);

        // Messages & Applications Deletion
        Route::delete('/admin/messages/{id}', [SiteContentApiController::class, 'destroyMessage']);
        Route::delete('/admin/applications/{id}', [CareerApiController::class, 'destroyApplication']);

        // Man Power / Employees Mutations (Mutasi, Tambah, Edit, Hapus, Import)
        Route::post('/admin/employees/import-batch', [EmployeeApiController::class, 'importBatch']);
        Route::delete('/admin/employees/all', [EmployeeApiController::class, 'destroyAll']);
        Route::post('/admin/employees/bulk-delete', [EmployeeApiController::class, 'bulkDelete']);
        Route::post('/admin/employees/normalize-pkwt', [EmployeeApiController::class, 'normalizePkwt']);
        Route::post('/admin/employees/repair-families', [EmployeeApiController::class, 'repairFamilies']);
        Route::post('/admin/employees', [EmployeeApiController::class, 'store']);
        Route::post('/admin/employees/{id}', [EmployeeApiController::class, 'update'])->whereNumber('id');
        Route::delete('/admin/employees/{id}', [EmployeeApiController::class, 'destroy'])->whereNumber('id');
        Route::post('/admin/employees/{id}/contracts', [EmployeeApiController::class, 'addContract'])->whereNumber('id');
        Route::delete('/admin/contracts/{id}', [EmployeeApiController::class, 'deleteContract'])->whereNumber('id');
        Route::post('/admin/employees/{id}/families', [EmployeeApiController::class, 'storeFamily'])->whereNumber('id');
        Route::put('/admin/families/{id}', [EmployeeApiController::class, 'updateFamily'])->whereNumber('id');
        Route::delete('/admin/families/{id}', [EmployeeApiController::class, 'destroyFamily'])->whereNumber('id');

        // Departments Mutation
        Route::post('/admin/departments', [EmployeeApiController::class, 'storeDepartment']);
        Route::put('/admin/departments/{id}', [EmployeeApiController::class, 'updateDepartment']);
        Route::delete('/admin/departments/{id}', [EmployeeApiController::class, 'destroyDepartment']);
    });
});
