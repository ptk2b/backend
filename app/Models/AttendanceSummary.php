<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AttendanceSummary extends Model
{
    use HasFactory;

    protected $table = 'attendance_summaries';

    protected $fillable = [
        'year',
        'month',
        'area_kerja',
        'kategori',
        'total_count',
        'catatan',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'year' => 'integer',
        'month' => 'integer',
        'total_count' => 'integer',
    ];

    /**
     * Standard 8 Area Kerja defined by K2B management.
     */
    const STANDARD_AREAS = [
        'SM-D PRODUKSI',
        'SM-C PRODUKSI',
        'PAKU/COAL PRODUKSI',
        'OVER BURDEN PRODUKSI',
        'OFFICE',
        'MEKANIK-ELECTIRC-WELDER-TYRE',
        'LOGISTIC-FUEL-CARPENTER',
        'SHE',
    ];

    /**
     * Standard Attendance Categories.
     */
    const CATEGORIES = [
        'SAKIT',
        'IJIN',
        'ALPA',
    ];

    /**
     * Indonesian 3-letter month abbreviations matching the company's Excel format.
     */
    const MONTH_LABELS = [
        1 => 'JAN',
        2 => 'FEB',
        3 => 'MAR',
        4 => 'APR',
        5 => 'MEI',
        6 => 'JUN',
        7 => 'JUL',
        8 => 'AGS',
        9 => 'SEP',
        10 => 'OKT',
        11 => 'NOP',
        12 => 'DES',
    ];

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function updater(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    /**
     * Populate default initial data from company's verified 2026 spreadsheet.
     */
    public static function seedInitial2026Data(): void
    {
        $initialData = [
            // 1. SM-D PRODUKSI
            ['area_kerja' => 'SM-D PRODUKSI', 'kategori' => 'SAKIT', 'month' => 5, 'total_count' => 89],
            ['area_kerja' => 'SM-D PRODUKSI', 'kategori' => 'SAKIT', 'month' => 6, 'total_count' => 108],
            ['area_kerja' => 'SM-D PRODUKSI', 'kategori' => 'SAKIT', 'month' => 7, 'total_count' => 104],

            ['area_kerja' => 'SM-D PRODUKSI', 'kategori' => 'IJIN', 'month' => 5, 'total_count' => 133],
            ['area_kerja' => 'SM-D PRODUKSI', 'kategori' => 'IJIN', 'month' => 6, 'total_count' => 143],
            ['area_kerja' => 'SM-D PRODUKSI', 'kategori' => 'IJIN', 'month' => 7, 'total_count' => 152],

            ['area_kerja' => 'SM-D PRODUKSI', 'kategori' => 'ALPA', 'month' => 5, 'total_count' => 4],
            ['area_kerja' => 'SM-D PRODUKSI', 'kategori' => 'ALPA', 'month' => 6, 'total_count' => 4],
            ['area_kerja' => 'SM-D PRODUKSI', 'kategori' => 'ALPA', 'month' => 7, 'total_count' => 2],

            // 2. SM-C PRODUKSI
            ['area_kerja' => 'SM-C PRODUKSI', 'kategori' => 'SAKIT', 'month' => 5, 'total_count' => 18],
            ['area_kerja' => 'SM-C PRODUKSI', 'kategori' => 'SAKIT', 'month' => 6, 'total_count' => 21],
            ['area_kerja' => 'SM-C PRODUKSI', 'kategori' => 'SAKIT', 'month' => 7, 'total_count' => 20],

            ['area_kerja' => 'SM-C PRODUKSI', 'kategori' => 'IJIN', 'month' => 5, 'total_count' => 25],
            ['area_kerja' => 'SM-C PRODUKSI', 'kategori' => 'IJIN', 'month' => 6, 'total_count' => 31],
            ['area_kerja' => 'SM-C PRODUKSI', 'kategori' => 'IJIN', 'month' => 7, 'total_count' => 27],

            ['area_kerja' => 'SM-C PRODUKSI', 'kategori' => 'ALPA', 'month' => 5, 'total_count' => 0],
            ['area_kerja' => 'SM-C PRODUKSI', 'kategori' => 'ALPA', 'month' => 6, 'total_count' => 1],
            ['area_kerja' => 'SM-C PRODUKSI', 'kategori' => 'ALPA', 'month' => 7, 'total_count' => 2],

            // 3. PAKU/COAL PRODUKSI
            ['area_kerja' => 'PAKU/COAL PRODUKSI', 'kategori' => 'SAKIT', 'month' => 5, 'total_count' => 36],
            ['area_kerja' => 'PAKU/COAL PRODUKSI', 'kategori' => 'SAKIT', 'month' => 6, 'total_count' => 48],
            ['area_kerja' => 'PAKU/COAL PRODUKSI', 'kategori' => 'SAKIT', 'month' => 7, 'total_count' => 43],

            ['area_kerja' => 'PAKU/COAL PRODUKSI', 'kategori' => 'IJIN', 'month' => 5, 'total_count' => 55],
            ['area_kerja' => 'PAKU/COAL PRODUKSI', 'kategori' => 'IJIN', 'month' => 6, 'total_count' => 54],
            ['area_kerja' => 'PAKU/COAL PRODUKSI', 'kategori' => 'IJIN', 'month' => 7, 'total_count' => 53],

            ['area_kerja' => 'PAKU/COAL PRODUKSI', 'kategori' => 'ALPA', 'month' => 5, 'total_count' => 0],
            ['area_kerja' => 'PAKU/COAL PRODUKSI', 'kategori' => 'ALPA', 'month' => 6, 'total_count' => 0],
            ['area_kerja' => 'PAKU/COAL PRODUKSI', 'kategori' => 'ALPA', 'month' => 7, 'total_count' => 2],

            // 4. OVER BURDEN PRODUKSI
            ['area_kerja' => 'OVER BURDEN PRODUKSI', 'kategori' => 'SAKIT', 'month' => 5, 'total_count' => 47],
            ['area_kerja' => 'OVER BURDEN PRODUKSI', 'kategori' => 'SAKIT', 'month' => 6, 'total_count' => 61],
            ['area_kerja' => 'OVER BURDEN PRODUKSI', 'kategori' => 'SAKIT', 'month' => 7, 'total_count' => 56],

            ['area_kerja' => 'OVER BURDEN PRODUKSI', 'kategori' => 'IJIN', 'month' => 5, 'total_count' => 63],
            ['area_kerja' => 'OVER BURDEN PRODUKSI', 'kategori' => 'IJIN', 'month' => 6, 'total_count' => 73],
            ['area_kerja' => 'OVER BURDEN PRODUKSI', 'kategori' => 'IJIN', 'month' => 7, 'total_count' => 65],

            ['area_kerja' => 'OVER BURDEN PRODUKSI', 'kategori' => 'ALPA', 'month' => 5, 'total_count' => 0],
            ['area_kerja' => 'OVER BURDEN PRODUKSI', 'kategori' => 'ALPA', 'month' => 6, 'total_count' => 2],
            ['area_kerja' => 'OVER BURDEN PRODUKSI', 'kategori' => 'ALPA', 'month' => 7, 'total_count' => 1],
        ];

        foreach ($initialData as $item) {
            self::updateOrCreate(
                [
                    'year' => 2026,
                    'month' => $item['month'],
                    'area_kerja' => $item['area_kerja'],
                    'kategori' => $item['kategori'],
                ],
                [
                    'total_count' => $item['total_count'],
                ]
            );
        }
    }
}
