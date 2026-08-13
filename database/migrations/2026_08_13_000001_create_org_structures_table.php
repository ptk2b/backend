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
        Schema::create('org_structures', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('role');
            $table->string('division')->default('Direksi');
            $table->integer('level')->default(1);
            $table->foreignId('parent_id')->nullable()->constrained('org_structures')->nullOnDelete();
            $table->string('photo_path')->nullable();
            $table->text('bio')->nullable();
            $table->text('responsibilities')->nullable();
            $table->integer('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('org_structures');
    }
};
