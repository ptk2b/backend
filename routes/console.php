<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Artisan::command('employees:repair-families', function () {
    $this->info("Memulai perbaikan relasi kepala keluarga/suami...");
    
    $controller = app(\App\Http\Controllers\Api\EmployeeApiController::class);
    $response = $controller->repairFamilies();
    $data = $response->getData(true);
    
    $this->info($data['message'] ?? 'Selesai.');
    if (!empty($data['details'])) {
        foreach ($data['details'] as $detail) {
            $this->line(" - " . $detail);
        }
    }
})->purpose('Perbaiki relasi kepala keluarga / suami yang terbaca di atas karyawan sebelumnya tanpa perlu upload ulang');

