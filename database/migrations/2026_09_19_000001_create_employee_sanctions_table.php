<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('employee_sanctions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('employee_id')->constrained('employees')->onDelete('cascade');
            $table->string('nomor_surat')->nullable()->index();
            
            // Tingkat Sanksi: SP1, SP2, SP3, PHK_PENSIUN, PHK_RESIGN, PHK
            $table->string('tingkat_sanksi')->index(); 
            
            // Jenis Pelanggaran: FATIGUE, INCAR, APD, ABSENSI, LAINNYA
            $table->string('jenis_pelanggaran')->index(); 
            
            // Snapshots of department & position when sanction is issued
            $table->string('departemen')->nullable()->index();
            $table->string('jabatan')->nullable()->index();
            
            // Dates
            $table->date('tanggal_sp')->index();
            $table->date('tanggal_berakhir')->nullable()->index();
            
            // Details & Files
            $table->text('alasan_pelanggaran')->nullable();
            $table->text('kronologi')->nullable();
            $table->string('file_sp_path')->nullable();
            
            // Status: AKTIF, EXPIRED, DICABUT, ESKALASI
            $table->string('status')->default('AKTIF')->index();
            $table->text('catatan')->nullable();
            
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('employee_sanctions');
    }
};
