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
        Schema::create('employee_comic_libraries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('employee_id')->constrained('employees')->cascadeOnDelete();
            $table->string('comic_slug')->index();
            $table->string('comic_title');
            $table->string('comic_thumb')->nullable();
            $table->string('comic_type')->default('manhwa'); // manhwa, manga, manhua
            $table->string('status')->default('reading')->index(); // reading, plan_to_read, completed, on_hold
            $table->string('last_chapter_slug')->nullable();
            $table->string('last_chapter_num')->nullable();
            $table->string('last_chapter_title')->nullable();
            $table->integer('scroll_progress')->default(0); // 0 - 100 percentage
            $table->json('read_chapters')->nullable(); // array of chapter slugs / numbers read
            $table->unsignedTinyInteger('rating')->nullable();
            $table->timestamp('last_read_at')->nullable()->index();
            $table->timestamps();

            $table->unique(['employee_id', 'comic_slug'], 'emp_comic_unique');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('employee_comic_libraries');
    }
};
