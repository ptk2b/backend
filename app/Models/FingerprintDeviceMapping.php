<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FingerprintDeviceMapping extends Model
{
    use HasFactory;

    protected $fillable = [
        'device_id',
        'finger_id',
        'nik',
        'nama',
        'jabatan',
    ];

    public function device(): BelongsTo
    {
        return $this->belongsTo(FingerprintDevice::class, 'device_id');
    }
}

