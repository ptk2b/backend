<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Builder;
use Carbon\Carbon;

class Employee extends Model
{
    protected $fillable = [
        // Kolom B - G
        'bendera',
        'kode',
        'pisat',
        'peserta',
        'nip',
        'jabatan',

        // Kolom H - Q (Pekerjaan & Kontrak)
        'departemen',
        'in',
        'outtoday',
        'outhal',
        'kontrak',
        'masa_kerja',
        'status_hubungan_kerja',
        'status_karyawan',
        'mutasi_pt_jabatan',
        'lama_mutasi',

        // Kolom R - W (Kontak & Pajak)
        'no_telp',
        'email',
        'npwp',
        'pendidikan_terakhir',
        'suku',
        'agama',

        // Kolom X - AH (Pribadi & Identitas)
        'nomor_kartu_keluarga',
        'nik',
        'nama_lengkap',
        'tempat_lahir',
        'tanggal_lahir',
        'usia',
        'jenis_kelamin',
        'status_kawin',
        'tanggal_perkawinan_perceraian',
        'lokal_nonlokal',
        'kewarganegaraan',

        // Kolom AI - AS (Alamat & Orang Tua)
        'alamat',
        'rt',
        'rw',
        'kelurahan',
        'kecamatan',
        'kabupaten',
        'provinsi',
        'kode_pos',
        'domisili',
        'nama_ayah',
        'nama_ibu',

        // Kolom AU - BQ (BPJS, Faskes, Payroll, Sub Cabang)
        'nomor_bpjstk',
        'nomor_bpjs_kis_peserta',
        'nomor_bpjs_kis_anggota_keluarga',
        'jenis_mutasi',
        'pisat_bpjs',
        'alamat_tempat_tinggal_bpjs',
        'kode_faskes_tk_1',
        'nama_faskes_tk_1',
        'kode_faskes_dokter_gigi',
        'nama_faskes_dokter_gigi',
        'nomor_telepon_rumus',
        'email_rumus',
        'npp',
        'gaji_pokok_tunjangan_tetap',
        'kewarganegaraan_bpjs',
        'sub_cabang',

        // Dokumen & Catatan
        'sk_path',
        'catatan',
    ];

    protected $casts = [
        'in'                            => 'date',
        'outtoday'                      => 'date',
        'tanggal_lahir'                 => 'date',
        'tanggal_perkawinan_perceraian' => 'date',
        'usia'                          => 'integer',
    ];

    /**
     * Auto-calculate usia, masa_kerja, and handle status on save.
     */
    protected static function booted(): void
    {
        static::saving(function (Employee $employee) {
            // 1. Auto-calculate usia from tanggal_lahir
            if ($employee->tanggal_lahir && empty($employee->usia)) {
                $employee->usia = Carbon::parse($employee->tanggal_lahir)->age;
            }

            // 2. Handle status_karyawan:
            // If status_karyawan is explicitly ACTIVE, ALWAYS preserve it (even if PKWT has expired)
            if ($employee->status_karyawan === 'ACTIVE') {
                // Keep ACTIVE as explicitly designated
            } elseif ($employee->status_karyawan === 'NON ACTIVE') {
                // Keep NON ACTIVE
            } elseif (!empty($employee->outhal) && trim($employee->outhal) !== '' && trim($employee->outhal) !== '-') {
                $employee->status_karyawan = 'NON ACTIVE';
            } else {
                $employee->status_karyawan = 'ACTIVE';
            }

            // 3. Auto-calculate masa_kerja if in (Tanggal Masuk) is set
            if ($employee->in) {
                try {
                    $start = Carbon::parse($employee->in);
                    $end = ($employee->status_karyawan === 'NON ACTIVE' && $employee->outtoday)
                        ? Carbon::parse($employee->outtoday)
                        : Carbon::now();

                    if ($end->greaterThanOrEqualTo($start)) {
                        $diff = $start->diff($end);
                        $parts = [];
                        if ($diff->y > 0) $parts[] = $diff->y . ' TAHUN';
                        if ($diff->m > 0) $parts[] = $diff->m . ' BULAN';
                        if ($diff->d > 0 && $diff->y === 0 && $diff->m === 0) $parts[] = $diff->d . ' HARI';

                        $employee->masa_kerja = !empty($parts) ? implode(' ', $parts) : '1 BULAN';
                    }
                } catch (\Exception $e) {
                    // Ignore date parse errors
                }
            }
        });
    }

    /**
     * Filter employees with contracts expiring within $days days or already passed OUTTODAY (PKWT active only).
     */
    public function scopeExpiringSoon(Builder $query, int $days = 30): Builder
    {
        return $query->where('status_karyawan', 'ACTIVE')
            ->where(function ($q) {
                $q->where('status_hubungan_kerja', 'PKWT')
                  ->orWhere('status_hubungan_kerja', 'like', '%kontrak%');
            })
            ->whereNotNull('outtoday')
            ->where('outtoday', '<=', Carbon::today()->addDays($days));
    }

    public function contractHistories(): HasMany
    {
        return $this->hasMany(ContractHistory::class)->orderBy('kontrak_ke', 'desc');
    }

    public function families(): HasMany
    {
        return $this->hasMany(EmployeeFamily::class, 'employee_id')->orderBy('id', 'asc');
    }
}
