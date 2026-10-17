<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EmployeeComicLibrary extends Model
{
    protected $fillable = [
        'employee_id',
        'comic_slug',
        'comic_title',
        'comic_thumb',
        'comic_type',
        'status',
        'last_chapter_slug',
        'last_chapter_num',
        'last_chapter_title',
        'scroll_progress',
        'read_chapters',
        'rating',
        'last_read_at',
    ];

    protected $casts = [
        'read_chapters' => 'array',
        'scroll_progress' => 'integer',
        'rating' => 'integer',
        'last_read_at' => 'datetime',
    ];

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }
}
