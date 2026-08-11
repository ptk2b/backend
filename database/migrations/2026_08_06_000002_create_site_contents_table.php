<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('site_contents', function (Blueprint $table) {
            $table->id();
            $table->string('section', 100);
            $table->string('content_key', 100);
            $table->string('lang', 5)->default('id');
            $table->longText('content_value');
            $table->string('content_type', 20)->default('text');
            $table->timestamps();

            $table->unique(['section', 'content_key', 'lang'], 'unique_content');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('site_contents');
    }
};
