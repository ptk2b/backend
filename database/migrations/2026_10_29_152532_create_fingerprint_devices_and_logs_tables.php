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
        // 1. Master Mesin Fingerprint / Lokasi Area Kerja
        Schema::create('fingerprint_devices', function (Blueprint $table) {
            $table->id();
            $table->string('code', 50)->unique(); // Contoh: 'PIT', 'WORKSHOP', 'PORT', 'OFFICE'
            $table->string('name', 100);          // Nama Mesin/Area (misal: "Mesin Finger Pit / Tambang")
            $table->string('location', 100)->nullable();
            $table->string('ip_address', 45)->nullable();
            $table->text('description')->nullable();
            $table->foreignId('assigned_user_id')->nullable()->constrained('users')->nullOnDelete(); // Admin penanggung jawab
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        // 2. Mapping ID Finger per Mesin ke NIK Karyawan
        // Kunci Unik: device_id + finger_id (sehingga ID 10 di Mesin 1 TIDAK AKAN BENTROK dengan ID 10 di Mesin 2)
        Schema::create('fingerprint_device_mappings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('device_id')->constrained('fingerprint_devices')->cascadeOnDelete();
            $table->unsignedBigInteger('finger_id'); // ID numeric pada mesin fingerprint
            $table->string('nik', 50);              // NIK karyawan K2B
            $table->string('nama', 150)->nullable();
            $table->string('jabatan', 100)->nullable(); // Driver, Foreman, SPV, dll.
            $table->timestamps();

            $table->unique(['device_id', 'finger_id'], 'uniq_device_finger_id');
            $table->index(['device_id', 'nik']);
        });

        // 3. Log Scan Mentah / Import .DAT per Mesin
        Schema::create('fingerprint_device_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('device_id')->constrained('fingerprint_devices')->cascadeOnDelete();
            $table->unsignedBigInteger('finger_id');
            $table->dateTime('scan_time');
            $table->unsignedTinyInteger('verify_type')->default(1);
            $table->unsignedTinyInteger('work_state')->default(0);
            $table->foreignId('uploaded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('batch_file', 255)->nullable();
            $table->timestamps();

            // Cegah duplikasi saat admin mengupload file log yang sama berulang kali
            $table->unique(['device_id', 'finger_id', 'scan_time'], 'uniq_device_finger_scan');
            $table->index(['device_id', 'scan_time']);
        });

        // 4. Rekap Evaluasi Harian Multi-Mesin
        Schema::create('fingerprint_device_rekap', function (Blueprint $table) {
            $table->id();
            $table->foreignId('device_id')->constrained('fingerprint_devices')->cascadeOnDelete();
            $table->string('periode', 7); // Format: 'YYYY-MM'
            $table->date('tanggal');
            $table->string('nik', 50);
            $table->string('nama', 150);
            $table->string('jabatan', 100)->nullable();
            $table->string('area', 100)->nullable();
            $table->string('kode_shift', 20)->nullable(); // S, M, LS, LM, OFF, STB
            $table->string('status_otomatis', 20);        // HADIR, TH, dll.
            $table->string('status_final', 20);           // Setelah override admin
            $table->string('status_override', 20)->nullable();
            $table->text('keterangan_admin')->nullable();
            $table->dateTime('clock_in')->nullable();
            $table->dateTime('clock_out')->nullable();
            $table->decimal('jam_kerja_eff', 5, 2)->default(0);
            $table->boolean('perlu_cek')->default(false);
            $table->timestamps();

            $table->unique(['device_id', 'nik', 'tanggal'], 'uniq_device_nik_date');
            $table->index(['periode', 'device_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('fingerprint_device_rekap');
        Schema::dropIfExists('fingerprint_device_logs');
        Schema::dropIfExists('fingerprint_device_mappings');
        Schema::dropIfExists('fingerprint_devices');
    }
};
