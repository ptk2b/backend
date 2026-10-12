<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class RfuLocation extends Model
{
    protected $fillable = [
        'name',
        'code',
        'rfu_count',
        'plan_count',
        'on_progress_count',
        'sort_order',
    ];

    protected $casts = [
        'rfu_count' => 'integer',
        'plan_count' => 'integer',
        'on_progress_count' => 'integer',
        'sort_order' => 'integer',
    ];

    public function unitRecords(): HasMany
    {
        return $this->hasMany(RfuUnitRecord::class, 'location_id');
    }

    public function activeUnits(): HasMany
    {
        return $this->hasMany(RfuUnitRecord::class, 'location_id')
            ->where('status', 'ON_PROGRESS')
            ->orderBy('id', 'asc');
    }
}
