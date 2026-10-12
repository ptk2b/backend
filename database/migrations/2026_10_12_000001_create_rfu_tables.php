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
        Schema::create('rfu_locations', function (Blueprint $table) {
            $table->id();
            $table->string('name')->unique();
            $table->string('code')->unique();
            $table->integer('rfu_count')->default(0);
            $table->integer('plan_count')->default(0);
            $table->integer('on_progress_count')->default(0);
            $table->integer('sort_order')->default(1);
            $table->timestamps();
        });

        Schema::create('rfu_unit_records', function (Blueprint $table) {
            $table->id();
            $table->foreignId('location_id')->constrained('rfu_locations')->onDelete('cascade');
            $table->string('position', 50)->comment('Base 1-12, Bay 1-6, Lapangan, dll');
            $table->date('tanggal_masuk');
            $table->string('jam_masuk', 10);
            $table->string('dt_number', 50)->index();
            $table->text('driver_complaint');
            $table->string('estimation_type', 50)->default('datetime');
            $table->dateTime('estimation_datetime')->nullable();
            $table->string('estimation_note', 255)->nullable();
            $table->enum('status', ['ON_PROGRESS', 'RFU'])->default('ON_PROGRESS')->index();
            $table->dateTime('completed_at')->nullable();
            $table->text('notes')->nullable();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('rfu_unit_records');
        Schema::dropIfExists('rfu_locations');
    }
};
