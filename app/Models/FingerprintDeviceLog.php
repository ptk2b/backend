<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FingerprintDeviceLog extends Model
{
    use HasFactory;

    protected $fillable = [
        'device_id',
        'finger_id',
        'scan_time',
        'verify_type',
        'work_state',
        'uploaded_by',
        'batch_file',
    ];

    protected function casts(): array
    {
        return [
            'scan_time' => 'datetime',
            'verify_type' => 'integer',
            'work_state' => 'integer',
        ];
    }

    public function device(): BelongsTo
    {
        return $this->belongsTo(FingerprintDevice::class, 'device_id');
    }

    public function uploader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }
}

