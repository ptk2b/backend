<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FingerprintDeviceRekap extends Model
{
    use HasFactory;

    protected $table = 'fingerprint_device_rekap';

    protected $fillable = [
        'device_id',
        'periode',
        'tanggal',
        'nik',
        'nama',
        'jabatan',
        'area',
        'kode_shift',
        'status_otomatis',
        'status_final',
        'status_override',
        'keterangan_admin',
        'clock_in',
        'clock_out',
        'jam_kerja_eff',
        'perlu_cek',
    ];

    protected function casts(): array
    {
        return [
            'tanggal' => 'date',
            'clock_in' => 'datetime',
            'clock_out' => 'datetime',
            'jam_kerja_eff' => 'decimal:2',
            'perlu_cek' => 'boolean',
        ];
    }

    public function device(): BelongsTo
    {
        return $this->belongsTo(FingerprintDevice::class, 'device_id');
    }
}

