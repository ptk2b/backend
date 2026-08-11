<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CareerApplication extends Model
{
    protected $fillable = [
        'career_id',
        'career_title',
        'name',
        'email',
        'phone',
        'cover_letter',
        'cv_path',
    ];

    public function career(): BelongsTo
    {
        return $this->belongsTo(Career::class);
    }
}
