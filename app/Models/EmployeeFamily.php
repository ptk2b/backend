<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Carbon\Carbon;

class EmployeeFamily extends Model
{
    protected $fillable = [
        'employee_id',
        'nama_lengkap',
        'hubungan',
        'pisat',
        'nik',
        'tempat_lahir',
        'tanggal_lahir',
        'usia',
        'jenis_kelamin',
        'status_kawin',
        'nomor_bpjs_kis',
        'kode_faskes_tk_1',
        'nama_faskes_tk_1',
        'kode_faskes_dokter_gigi',
        'nama_faskes_dokter_gigi',
        'alamat',
        'catatan',
    ];

    protected $casts = [
        'tanggal_lahir' => 'date',
        'usia'          => 'integer',
    ];

    protected static function booted(): void
    {
        static::saving(function (EmployeeFamily $family) {
            if ($family->tanggal_lahir && empty($family->usia)) {
                $family->usia = Carbon::parse($family->tanggal_lahir)->age;
            }
        });
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }
}
