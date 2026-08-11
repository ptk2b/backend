<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('memos', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->string('memo_number', 100)->nullable();
            $table->string('category', 100)->default('General');
            $table->text('description')->nullable();
            $table->string('file_path', 512);
            $table->string('file_name');
            $table->unsignedInteger('file_size')->nullable();
            $table->foreignId('uploaded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('memos');
    }
};
