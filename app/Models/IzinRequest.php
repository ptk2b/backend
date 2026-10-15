<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class IzinRequest extends Model
{
    protected $guarded = [];

    protected $with = ['employee', 'supervisor', 'decider'];

    protected $casts = [
        'mulai' => 'date:Y-m-d',
        'selesai' => 'date:Y-m-d',
        'decided_at' => 'datetime',
    ];

    public function employee()
    {
        return $this->belongsTo(Employee::class);
    }

    public function supervisor()
    {
        return $this->belongsTo(Employee::class, 'supervisor_id');
    }

    public function decider()
    {
        return $this->belongsTo(Employee::class, 'decided_by');
    }
}
