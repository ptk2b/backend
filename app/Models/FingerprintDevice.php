<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class FingerprintDevice extends Model
{
    use HasFactory;

    protected $fillable = [
        'code',
        'name',
        'location',
        'ip_address',
        'description',
        'assigned_user_id',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
        ];
    }

    public function assignedUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_user_id');
    }

    public function mappings(): HasMany
    {
        return $this->hasMany(FingerprintDeviceMapping::class, 'device_id');
    }

    public function logs(): HasMany
    {
        return $this->hasMany(FingerprintDeviceLog::class, 'device_id');
    }

    public function rekap(): HasMany
    {
        return $this->hasMany(FingerprintDeviceRekap::class, 'device_id');
    }
}

