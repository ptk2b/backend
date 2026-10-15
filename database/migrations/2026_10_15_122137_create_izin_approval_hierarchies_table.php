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
        Schema::create('izin_approval_hierarchies', function (Blueprint $table) {
            $table->id();
            $table->string('keyword')->unique(); // contoh: FOREMAN, SUPERINTENDENT, DEPUTY
            $table->string('label')->nullable(); // contoh: Foreman Lapangan
            $table->tinyInteger('level')->default(1); // 1 = Tingkat 1, 2 = Tingkat 2, 3 = Deputy/Direksi, 4 = PJO
            $table->string('keterangan')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('izin_approval_hierarchies');
    }
};
