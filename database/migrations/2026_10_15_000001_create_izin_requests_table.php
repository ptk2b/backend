<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('employees', function (Blueprint $table) {
            $table->boolean('dilokasi')->default(false);
        });

        Schema::create('izin_requests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('employee_id')->constrained('employees')->cascadeOnDelete();
            $table->foreignId('supervisor_id')->nullable()->constrained('employees')->nullOnDelete();
            $table->string('jenis', 10); // sakit | cuti | izin
            $table->date('mulai');
            $table->date('selesai');
            $table->unsignedSmallInteger('durasi');
            $table->string('alasan', 140);
            $table->string('lampiran_path')->nullable();
            $table->string('status', 15)->default('menunggu')->index(); // menunggu|disetujui|ditolak|dibatalkan
            $table->string('catatan')->nullable();
            $table->timestamp('decided_at')->nullable();
            $table->foreignId('decided_by')->nullable()->constrained('employees')->nullOnDelete();
            $table->timestamps();

            $table->index(['employee_id', 'mulai', 'selesai']);
            $table->index(['supervisor_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('izin_requests');
        Schema::table('employees', function (Blueprint $table) {
            $table->dropColumn('dilokasi');
        });
    }
};
