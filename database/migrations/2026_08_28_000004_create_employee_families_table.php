<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('employee_families', function (Blueprint $table) {
            $table->id();
            $table->foreignId('employee_id')->constrained('employees')->onDelete('cascade');
            
            $table->string('nama_lengkap');
            $table->string('hubungan')->nullable(); // ISTRI, SUAMI, ANAK 1, ANAK 2, dll
            $table->string('pisat')->nullable(); // 1=PESERTA, 2=SUAMI, 3=ISTRI, 4=ANAK
            $table->string('nik')->nullable()->index();
            $table->string('tempat_lahir')->nullable();
            $table->date('tanggal_lahir')->nullable();
            $table->integer('usia')->nullable();
            $table->string('jenis_kelamin')->nullable();
            $table->string('status_kawin')->nullable();
            
            // BPJS & Faskes Tanggungan
            $table->string('nomor_bpjs_kis')->nullable();
            $table->string('kode_faskes_tk_1')->nullable();
            $table->string('nama_faskes_tk_1')->nullable();
            $table->string('kode_faskes_dokter_gigi')->nullable();
            $table->string('nama_faskes_dokter_gigi')->nullable();
            $table->text('alamat')->nullable();
            $table->text('catatan')->nullable();

            $table->timestamps();

            $table->index('employee_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('employee_families');
    }
};
