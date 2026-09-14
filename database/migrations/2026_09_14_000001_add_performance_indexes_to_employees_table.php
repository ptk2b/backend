<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     * Add indexes safely if they do not already exist to speed up filter queries.
     */
    public function up(): void
    {
        $existing = $this->getExistingIndexes('employees');

        $indexes = [
            'employees_status_karyawan_index' => 'status_karyawan',
            'employees_status_hubungan_kerja_index' => 'status_hubungan_kerja',
            'employees_departemen_index' => 'departemen',
            'employees_jabatan_index' => 'jabatan',
            'employees_lokal_nonlokal_index' => 'lokal_nonlokal',
            'employees_jenis_kelamin_index' => 'jenis_kelamin',
            'employees_pendidikan_terakhir_index' => 'pendidikan_terakhir',
        ];

        foreach ($indexes as $indexName => $column) {
            if (!in_array($indexName, $existing, true)) {
                try {
                    Schema::table('employees', function (Blueprint $table) use ($column, $indexName) {
                        $table->index($column, $indexName);
                    });
                } catch (\Throwable $e) {
                    // Ignore duplicate key or existing index
                }
            }
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        $existing = $this->getExistingIndexes('employees');

        // Only drop indexes newly introduced by this migration
        $indexesToDrop = [
            'employees_lokal_nonlokal_index',
            'employees_jenis_kelamin_index',
            'employees_pendidikan_terakhir_index',
        ];

        foreach ($indexesToDrop as $indexName) {
            if (in_array($indexName, $existing, true)) {
                try {
                    Schema::table('employees', function (Blueprint $table) use ($indexName) {
                        $table->dropIndex($indexName);
                    });
                } catch (\Throwable $e) {
                    // Ignore
                }
            }
        }
    }

    /**
     * Safely retrieve all existing index names for a table.
     */
    private function getExistingIndexes(string $table): array
    {
        $existing = [];

        try {
            if (method_exists(Schema::class, 'getIndexes')) {
                $indexes = Schema::getIndexes($table);
                foreach ($indexes as $idx) {
                    if (is_array($idx) && isset($idx['name'])) {
                        $existing[] = $idx['name'];
                    } elseif (is_object($idx) && isset($idx->name)) {
                        $existing[] = $idx->name;
                    }
                }
            }
        } catch (\Throwable $e) {
            // Fallback to raw query
        }

        if (empty($existing)) {
            try {
                $raw = DB::select("SHOW INDEX FROM `{$table}`");
                foreach ($raw as $row) {
                    $rowObj = (object) $row;
                    $keyName = $rowObj->Key_name ?? $rowObj->key_name ?? null;
                    if ($keyName) {
                        $existing[] = $keyName;
                    }
                }
            } catch (\Throwable $e) {
                // Ignore
            }
        }

        return array_unique($existing);
    }
};
