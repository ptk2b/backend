<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('unit_workshops', function (Blueprint $table) {
            $table->id();
            $table->string('area', 20)->index()->comment('petrosea, pama, spaku, sims');
            $table->string('no_unit', 50)->unique();
            $table->string('merk', 100)->nullable();
            $table->string('type', 100)->nullable();
            $table->string('chassis_no', 100)->nullable();
            $table->string('engine_no', 100)->nullable();
            $table->string('job', 100)->nullable();
            $table->string('tahun', 10)->nullable();
            $table->text('trouble')->nullable();
            $table->date('breakdown_date')->nullable();
            $table->string('breakdown_time', 10)->nullable();
            $table->string('severity', 10)->nullable()->comment('MAJOR / MINOR');
            $table->text('keterangan')->nullable();
            $table->text('waiting_part')->nullable();
            $table->string('job_location', 100)->nullable();
            $table->string('ready', 100)->nullable();
            $table->string('status', 10)->default('RFU')->index()->comment('MAJOR / W/P / RFU');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('unit_workshops');
    }
};
