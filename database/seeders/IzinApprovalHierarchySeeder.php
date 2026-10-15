<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\IzinApprovalHierarchy;

class IzinApprovalHierarchySeeder extends Seeder
{
    public function run(): void
    {
        $defaults = [
            ['keyword' => 'PJO', 'label' => 'PJO (Penanggung Jawab Operasional)', 'level' => 4, 'keterangan' => 'Pimpinan Tertinggi Site'],
            ['keyword' => 'DEPUTY', 'label' => 'Deputy Project Manager / PJO', 'level' => 3, 'keterangan' => 'Wakil Pimpinan Operasional'],
            ['keyword' => 'SUPERINTENDENT', 'label' => 'Superintendent', 'level' => 2, 'keterangan' => 'Kepala Bagian / Departemen'],
            ['keyword' => 'PENANGGUNG JAWAB', 'label' => 'Penanggung Jawab', 'level' => 2, 'keterangan' => 'Penanggung Jawab Divisi'],
            ['keyword' => 'KABAG', 'label' => 'Kepala Bagian (Kabag)', 'level' => 2, 'keterangan' => 'Kepala Bagian'],
            ['keyword' => 'MANAGER', 'label' => 'Manager', 'level' => 2, 'keterangan' => 'Manager Departemen'],
            ['keyword' => 'FOREMAN', 'label' => 'Foreman Lapangan', 'level' => 1, 'keterangan' => 'Pengawas Lapangan'],
            ['keyword' => 'SUPERVISOR', 'label' => 'Supervisor', 'level' => 1, 'keterangan' => 'Supervisor Lapangan'],
            ['keyword' => 'LEADER', 'label' => 'Group Leader (GL)', 'level' => 1, 'keterangan' => 'Leader Tim Lapangan'],
            ['keyword' => 'KORDINATOR', 'label' => 'Kordinator', 'level' => 1, 'keterangan' => 'Kordinator Regu / Unit'],
            ['keyword' => 'KOORDINATOR', 'label' => 'Koordinator', 'level' => 1, 'keterangan' => 'Koordinator Lapangan'],
        ];

        foreach ($defaults as $d) {
            IzinApprovalHierarchy::firstOrCreate(['keyword' => $d['keyword']], $d);
        }
    }
}
