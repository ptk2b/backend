<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class IzinRequest extends Model
{
    protected $guarded = [];

    protected $with = ['employee', 'supervisor', 'decider', 'step1By', 'step2By'];

    protected $casts = [
        'mulai' => 'date:Y-m-d',
        'selesai' => 'date:Y-m-d',
        'decided_at' => 'datetime',
        'step_1_at' => 'datetime',
        'step_2_at' => 'datetime',
    ];

    public function employee()
    {
        return $this->belongsTo(Employee::class);
    }

    public function supervisor()
    {
        return $this->belongsTo(Employee::class, 'supervisor_id');
    }

    public function step1By()
    {
        return $this->belongsTo(Employee::class, 'step_1_by');
    }

    public function step2By()
    {
        return $this->belongsTo(Employee::class, 'step_2_by');
    }

    public function decider()
    {
        return $this->belongsTo(Employee::class, 'decided_by');
    }
}
