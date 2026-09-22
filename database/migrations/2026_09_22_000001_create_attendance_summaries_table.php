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
        Schema::create('attendance_summaries', function (Blueprint $table) {
            $table->id();
            $table->unsignedSmallInteger('year')->default(2026);
            $table->unsignedTinyInteger('month'); // 1 - 12
            $table->string('area_kerja', 100);
            $table->string('kategori', 50); // SAKIT, IJIN, ALPA
            $table->unsignedInteger('total_count')->default(0);
            $table->text('catatan')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['year', 'month', 'area_kerja', 'kategori'], 'uniq_att_summary_yr_mo_area_kat');
            $table->index(['year', 'area_kerja']);
            $table->index(['year', 'month']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('attendance_summaries');
    }
};
