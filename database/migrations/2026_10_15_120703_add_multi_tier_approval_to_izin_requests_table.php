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
        Schema::table('izin_requests', function (Blueprint $table) {
            // Tahap 1 (Foreman / Leader / Kordinator / Supervisor)
            $table->foreignId('step_1_by')->nullable()->after('supervisor_id')->constrained('employees')->nullOnDelete();
            $table->timestamp('step_1_at')->nullable()->after('step_1_by');
            $table->string('step_1_status', 20)->nullable()->after('step_1_at'); // disetujui / ditolak
            $table->string('step_1_catatan', 200)->nullable()->after('step_1_status');

            // Tahap 2 (Superintendent / Kabag / Deputy / PJO)
            $table->foreignId('step_2_by')->nullable()->after('step_1_catatan')->constrained('employees')->nullOnDelete();
            $table->timestamp('step_2_at')->nullable()->after('step_2_by');
            $table->string('step_2_status', 20)->nullable()->after('step_2_at'); // disetujui / ditolak
            $table->string('step_2_catatan', 200)->nullable()->after('step_2_status');

            // Tahap yang sedang aktif ('tahap_1', 'tahap_2', 'selesai')
            $table->string('current_step', 20)->default('tahap_1')->after('step_2_catatan');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('izin_requests', function (Blueprint $table) {
            $table->dropForeign(['step_1_by']);
            $table->dropForeign(['step_2_by']);
            $table->dropColumn([
                'step_1_by', 'step_1_at', 'step_1_status', 'step_1_catatan',
                'step_2_by', 'step_2_at', 'step_2_status', 'step_2_catatan',
                'current_step'
            ]);
        });
    }
};
