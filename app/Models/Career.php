<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Career extends Model
{
    protected $fillable = [
        'title',
        'department',
        'location',
        'type',
        'description',
        'requirements',
        'closed_date',
        'is_urgent',
        'is_active',
    ];

    protected $casts = [
        'is_urgent'   => 'boolean',
        'is_active'   => 'boolean',
        'closed_date' => 'date',
    ];

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    public function applications(): HasMany
    {
        return $this->hasMany(CareerApplication::class);
    }
}
