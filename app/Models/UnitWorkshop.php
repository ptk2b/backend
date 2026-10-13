<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class UnitWorkshop extends Model
{
    public const AREAS = [
        'petrosea' => 'Petrosea',
        'pama' => 'Pama',
        'sims' => 'SIMS',
        'spaku' => 'S. Paku',
    ];

    /** Kolom yang diimpor dari Excel (selain area & no_unit). */
    public const IMPORT_FIELDS = [
        'merk', 'type', 'chassis_no', 'engine_no', 'job', 'tahun', 'trouble',
        'breakdown_date', 'breakdown_time', 'severity', 'keterangan',
        'waiting_part', 'job_location', 'ready', 'status',
    ];

    protected $fillable = [
        'area', 'no_unit', 'merk', 'type', 'chassis_no', 'engine_no', 'job', 'tahun',
        'trouble', 'breakdown_date', 'breakdown_time', 'severity', 'keterangan',
        'waiting_part', 'job_location', 'ready', 'status',
    ];

    protected $casts = [
        'breakdown_date' => 'date:Y-m-d',
    ];

    /**
     * Hitungan per area: total unit, RFU, W/P, MAJOR (keyed by kode area).
     */
    public static function areaSummary(): array
    {
        $rows = static::query()
            ->selectRaw('area, count(*) as total, sum(status = ?) as rfu, sum(status = ?) as wp, sum(status = ?) as major', ['RFU', 'W/P', 'MAJOR'])
            ->groupBy('area')
            ->get()
            ->keyBy('area');

        $out = [];
        foreach (self::AREAS as $code => $name) {
            $r = $rows->get($code);
            $out[$code] = [
                'name' => $name,
                'total' => (int) ($r->total ?? 0),
                'rfu' => (int) ($r->rfu ?? 0),
                'wp' => (int) ($r->wp ?? 0),
                'major' => (int) ($r->major ?? 0),
            ];
        }
        return $out;
    }
}
