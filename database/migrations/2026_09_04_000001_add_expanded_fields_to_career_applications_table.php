<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('career_applications')) {
            Schema::table('career_applications', function (Blueprint $table) {
                if (!Schema::hasColumn('career_applications', 'tempat_lahir')) {
                    $table->string('tempat_lahir')->nullable()->after('phone');
                }
                if (!Schema::hasColumn('career_applications', 'tanggal_lahir')) {
                    $table->string('tanggal_lahir')->nullable()->after('tempat_lahir');
                }
                if (!Schema::hasColumn('career_applications', 'alamat_domisili')) {
                    $table->text('alamat_domisili')->nullable()->after('tanggal_lahir');
                }
                if (!Schema::hasColumn('career_applications', 'pendidikan_terakhir')) {
                    $table->string('pendidikan_terakhir')->nullable()->after('alamat_domisili');
                }
                if (!Schema::hasColumn('career_applications', 'nama_lembaga')) {
                    $table->string('nama_lembaga')->nullable()->after('pendidikan_terakhir');
                }
                if (!Schema::hasColumn('career_applications', 'sertifikasi')) {
                    $table->text('sertifikasi')->nullable()->after('nama_lembaga');
                }
                if (!Schema::hasColumn('career_applications', 'pengalaman_terakhir')) {
                    $table->string('pengalaman_terakhir')->nullable()->after('sertifikasi');
                }
                if (!Schema::hasColumn('career_applications', 'jabatan_terakhir')) {
                    $table->string('jabatan_terakhir')->nullable()->after('pengalaman_terakhir');
                }
                if (!Schema::hasColumn('career_applications', 'masa_kerja')) {
                    $table->string('masa_kerja')->nullable()->after('jabatan_terakhir');
                }
                if (!Schema::hasColumn('career_applications', 'rekomendasi')) {
                    $table->string('rekomendasi')->nullable()->after('masa_kerja');
                }
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('career_applications')) {
            Schema::table('career_applications', function (Blueprint $table) {
                $cols = [
                    'tempat_lahir',
                    'tanggal_lahir',
                    'alamat_domisili',
                    'pendidikan_terakhir',
                    'nama_lembaga',
                    'sertifikasi',
                    'pengalaman_terakhir',
                    'jabatan_terakhir',
                    'masa_kerja',
                    'rekomendasi',
                ];
                foreach ($cols as $col) {
                    if (Schema::hasColumn('career_applications', $col)) {
                        $table->dropColumn($col);
                    }
                }
            });
        }
    }
};
