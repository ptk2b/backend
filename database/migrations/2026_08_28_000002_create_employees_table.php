<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::dropIfExists('contract_histories');
        Schema::dropIfExists('employees');

        Schema::create('employees', function (Blueprint $table) {
            $table->id();

            // Kolom B - G
            $table->string('bendera')->nullable();
            $table->string('kode')->nullable();
            $table->string('pisat')->nullable();
            $table->string('peserta')->nullable();
            $table->string('nip')->nullable()->index();
            $table->string('jabatan')->nullable()->index();

            // Kolom H - Q (Kontrak & Mutasi)
            $table->string('departemen')->nullable()->index();
            $table->date('in')->nullable();
            $table->date('outtoday')->nullable()->index();
            $table->string('outhal')->nullable();
            $table->string('kontrak')->nullable();
            $table->string('masa_kerja')->nullable();
            $table->string('status_hubungan_kerja')->default('PKWT')->index();
            $table->string('status_karyawan')->default('ACTIVE')->index();
            $table->string('mutasi_pt_jabatan')->nullable();
            $table->string('lama_mutasi')->nullable();

            // Kolom R - W (Kontak & Pendidikan)
            $table->string('no_telp')->nullable();
            $table->string('email')->nullable();
            $table->string('npwp')->nullable();
            $table->string('pendidikan_terakhir')->nullable();
            $table->string('suku')->nullable();
            $table->string('agama')->nullable();

            // Kolom X - AH (Identitas & Pribadi)
            $table->string('nomor_kartu_keluarga')->nullable();
            $table->string('nik')->nullable()->index();
            $table->string('nama_lengkap')->index();
            $table->string('tempat_lahir')->nullable();
            $table->date('tanggal_lahir')->nullable();
            $table->integer('usia')->nullable();
            $table->string('jenis_kelamin')->nullable();
            $table->string('status_kawin')->nullable();
            $table->date('tanggal_perkawinan_perceraian')->nullable();
            $table->string('lokal_nonlokal')->nullable();
            $table->string('kewarganegaraan')->default('WNI');

            // Kolom AI - AS (Alamat & Orang Tua)
            $table->text('alamat')->nullable();
            $table->string('rt')->nullable();
            $table->string('rw')->nullable();
            $table->string('kelurahan')->nullable();
            $table->string('kecamatan')->nullable();
            $table->string('kabupaten')->nullable();
            $table->string('provinsi')->nullable();
            $table->string('kode_pos')->nullable();
            $table->string('domisili')->nullable();
            $table->string('nama_ayah')->nullable();
            $table->string('nama_ibu')->nullable();

            // Kolom AU - BQ (BPJS, Faskes, Payroll & Sub Cabang)
            $table->string('nomor_bpjstk')->nullable();
            $table->string('nomor_bpjs_kis_peserta')->nullable();
            $table->string('nomor_bpjs_kis_anggota_keluarga')->nullable();
            $table->string('jenis_mutasi')->nullable();
            $table->string('pisat_bpjs')->nullable();
            $table->string('alamat_tempat_tinggal_bpjs')->nullable();
            $table->string('kode_faskes_tk_1')->nullable();
            $table->string('nama_faskes_tk_1')->nullable();
            $table->string('kode_faskes_dokter_gigi')->nullable();
            $table->string('nama_faskes_dokter_gigi')->nullable();
            $table->string('nomor_telepon_rumus')->nullable();
            $table->string('email_rumus')->nullable();
            $table->string('npp')->nullable();
            $table->string('gaji_pokok_tunjangan_tetap')->nullable();
            $table->string('kewarganegaraan_bpjs')->nullable(); // Kolom BP
            $table->string('sub_cabang')->nullable(); // Kolom BQ

            // Dokumen & Catatan Internal
            $table->string('sk_path')->nullable();
            $table->text('catatan')->nullable();

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('employees');
    }
};
