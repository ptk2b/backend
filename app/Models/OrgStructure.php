<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class OrgStructure extends Model
{
    protected $fillable = [
        'name',
        'role',
        'division',
        'level',
        'parent_id',
        'photo_path',
        'bio',
        'responsibilities',
        'sort_order',
        'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'level'      => 'integer',
        'sort_order' => 'integer',
        'parent_id'  => 'integer',
    ];

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    public function parent(): BelongsTo
    {
        return $this->belongsTo(OrgStructure::class, 'parent_id');
    }

    public function children(): HasMany
    {
        return $this->hasMany(OrgStructure::class, 'parent_id')->orderBy('sort_order', 'asc');
    }
}
