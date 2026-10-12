<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RfuUnitRecord extends Model
{
    protected $fillable = [
        'location_id',
        'position',
        'tanggal_masuk',
        'jam_masuk',
        'dt_number',
        'driver_complaint',
        'estimation_type',
        'estimation_datetime',
        'estimation_note',
        'status',
        'completed_at',
        'notes',
        'created_by',
    ];

    protected $casts = [
        'tanggal_masuk' => 'date',
        'estimation_datetime' => 'datetime',
        'completed_at' => 'datetime',
    ];

    protected $appends = [
        'formatted_estimation',
    ];

    public function location(): BelongsTo
    {
        return $this->belongsTo(RfuLocation::class, 'location_id');
    }

    public function getFormattedEstimationAttribute(): string
    {
        if ($this->estimation_type === 'waiting_part') {
            return $this->estimation_note ? "Waiting Part: {$this->estimation_note}" : 'Waiting Part';
        }

        if ($this->estimation_type === 'waiting_bay') {
            return $this->estimation_note ? "Waiting Bay / Antri: {$this->estimation_note}" : 'Waiting Bay / Antri';
        }

        if ($this->estimation_type === 'overhaul') {
            return $this->estimation_note ? "Overhaul ({$this->estimation_note})" : 'Overhaul / Pekerjaan Besar';
        }

        if ($this->estimation_datetime) {
            $date = \Carbon\Carbon::parse($this->estimation_datetime);
            $formatted = $date->translatedFormat('d M Y H:i');
            return $this->estimation_note ? "{$formatted} ({$this->estimation_note})" : $formatted;
        }

        return $this->estimation_note ?: '-';
    }
}
