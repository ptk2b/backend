<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ContractHistory extends Model
{
    protected $fillable = [
        'employee_id', 'kontrak_ke',
        'tanggal_mulai', 'tanggal_selesai',
        'masa_kontrak_bulan', 'sk_path', 'catatan',
    ];

    protected $casts = [
        'tanggal_mulai'      => 'date',
        'tanggal_selesai'    => 'date',
        'masa_kontrak_bulan' => 'integer',
        'kontrak_ke'         => 'integer',
    ];

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }
}
