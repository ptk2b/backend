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
        Schema::table('contract_histories', function (Blueprint $table) {
            $table->string('diserahkan', 50)->nullable()->after('catatan');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('contract_histories', function (Blueprint $table) {
            $table->dropColumn('diserahkan');
        });
    }
};
