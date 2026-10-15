<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class IzinApprovalHierarchy extends Model
{
    protected $table = 'izin_approval_hierarchies';

    protected $guarded = [];

    protected $casts = [
        'level' => 'integer',
        'is_active' => 'boolean',
    ];
}
