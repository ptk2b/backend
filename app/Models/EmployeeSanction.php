<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Carbon\Carbon;

class EmployeeSanction extends Model
{
    protected $fillable = [
        'employee_id',
        'nomor_surat',
        'tingkat_sanksi',
        'jenis_pelanggaran',
        'departemen',
        'jabatan',
        'tanggal_sp',
        'tanggal_berakhir',
        'alasan_pelanggaran',
        'kronologi',
        'file_sp_path',
        'status',
        'catatan',
        'created_by',
    ];

    protected $casts = [
        'tanggal_sp'       => 'date',
        'tanggal_berakhir' => 'date',
    ];

    protected $appends = [
        'is_active',
        'days_remaining',
        'status_computed',
    ];

    protected static function booted(): void
    {
        static::saving(function (EmployeeSanction $sanction) {
            // Auto calculate 6 months for SP if tanggal_berakhir is not explicitly set
            if ($sanction->tanggal_sp && empty($sanction->tanggal_berakhir)) {
                if (in_array(strtoupper($sanction->tingkat_sanksi), ['SP1', 'SP2', 'SP3', 'SP 1', 'SP 2', 'SP 3'])) {
                    $sanction->tanggal_berakhir = Carbon::parse($sanction->tanggal_sp)->addMonths(6)->subDay();
                }
            }

            // If departemen / jabatan not supplied, snapshot from employee
            if ($sanction->employee_id && (empty($sanction->departemen) || empty($sanction->jabatan))) {
                $emp = Employee::find($sanction->employee_id);
                if ($emp) {
                    if (empty($sanction->departemen)) {
                        $sanction->departemen = $emp->departemen;
                    }
                    if (empty($sanction->jabatan)) {
                        $sanction->jabatan = $emp->jabatan;
                    }
                }
            }
        });
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * Compute if sanction is still active today.
     */
    public function getIsActiveAttribute(): bool
    {
        if (in_array(strtoupper($this->status), ['DICABUT', 'ESKALASI'])) {
            return false;
        }

        if (in_array(strtoupper($this->tingkat_sanksi), ['PHK', 'PHK_PENSIUN', 'PHK_RESIGN', 'PHK SANKSI'])) {
            return true; // PHK is permanent
        }

        if (!$this->tanggal_berakhir) {
            return true;
        }

        return Carbon::today()->lte(Carbon::parse($this->tanggal_berakhir));
    }

    /**
     * Compute remaining active days until expiration.
     */
    public function getDaysRemainingAttribute(): ?int
    {
        if (!$this->tanggal_berakhir) {
            return null;
        }

        $end = Carbon::parse($this->tanggal_berakhir);
        $today = Carbon::today();

        if ($today->gt($end)) {
            return -$today->diffInDays($end); // Negative if passed
        }

        return (int) $today->diffInDays($end);
    }

    /**
     * Dynamic status label: AKTIF, EXPIRED, DICABUT, ESKALASI, PHK
     */
    public function getStatusComputedAttribute(): string
    {
        if (in_array(strtoupper($this->status), ['DICABUT', 'ESKALASI'])) {
            return strtoupper($this->status);
        }

        if (in_array(strtoupper($this->tingkat_sanksi), ['PHK', 'PHK_PENSIUN', 'PHK_RESIGN', 'PHK SANKSI'])) {
            return 'PHK';
        }

        if (!$this->tanggal_berakhir) {
            return 'AKTIF';
        }

        return Carbon::today()->lte(Carbon::parse($this->tanggal_berakhir)) ? 'AKTIF' : 'EXPIRED';
    }
}
