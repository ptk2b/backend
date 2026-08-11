<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('careers', function (Blueprint $table) {
            $table->text('description')->nullable()->after('type');
            $table->text('requirements')->nullable()->after('description');
            $table->date('closed_date')->nullable()->after('requirements');
        });
    }

    public function down(): void
    {
        Schema::table('careers', function (Blueprint $table) {
            $table->dropColumn(['description', 'requirements', 'closed_date']);
        });
    }
};
