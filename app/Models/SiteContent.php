<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Builder;

class SiteContent extends Model
{
    protected $fillable = [
        'section',
        'content_key',
        'lang',
        'content_value',
        'content_type',
    ];

    public function scopeForSection(Builder $query, string $section, ?string $lang = null): Builder
    {
        $query->where('section', $section);
        if ($lang) {
            $query->where('lang', $lang);
        }
        return $query;
    }
}
