<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('contract_histories', function (Blueprint $table) {
            $table->id();
            $table->foreignId('employee_id')->constrained('employees')->onDelete('cascade');
            $table->integer('kontrak_ke');
            $table->date('tanggal_mulai');
            $table->date('tanggal_selesai');
            $table->integer('masa_kontrak_bulan');
            $table->string('sk_path')->nullable();
            $table->text('catatan')->nullable();
            $table->timestamps();

            $table->index('employee_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('contract_histories');
    }
};
