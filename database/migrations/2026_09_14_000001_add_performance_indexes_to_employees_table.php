<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     * Add indexes to speed up filter queries and conditional aggregations.
     */
    public function up(): void
    {
        Schema::table('employees', function (Blueprint $table) {
            $table->index('status_karyawan');
            $table->index('status_hubungan_kerja');
            $table->index('departemen');
            $table->index('jabatan');
            $table->index('lokal_nonlokal');
            $table->index('jenis_kelamin');
            $table->index('pendidikan_terakhir');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('employees', function (Blueprint $table) {
            $table->dropIndex(['status_karyawan']);
            $table->dropIndex(['status_hubungan_kerja']);
            $table->dropIndex(['departemen']);
            $table->dropIndex(['jabatan']);
            $table->dropIndex(['lokal_nonlokal']);
            $table->dropIndex(['jenis_kelamin']);
            $table->dropIndex(['pendidikan_terakhir']);
        });
    }
};
