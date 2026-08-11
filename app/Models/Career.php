<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Builder;

class Career extends Model
{
    protected $fillable = [
        'title',
        'department',
        'location',
        'type',
        'is_urgent',
        'is_active',
    ];

    protected $casts = [
        'is_urgent' => 'boolean',
        'is_active' => 'boolean',
    ];

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }
}
