<?php

namespace Database\Seeders;

use App\Models\OrgStructure;
use Illuminate\Database\Seeder;

class OrgStructureSeeder extends Seeder
{
    public function run(): void
    {
        if (OrgStructure::count() === 0) {
            $data = [
                [
                    'name' => 'Drs. H. Ahmad Sudrajat, M.M.',
                    'role' => 'Komisaris Utama',
                    'division' => 'Dewan Komisaris',
                    'level' => 1,
                    'parent_id' => null,
                    'photo_path' => null,
                    'bio' => 'Berpengalaman lebih dari 25 tahun dalam pengawasan tata kelola perusahaan dan kepemimpinan strategis.',
                    'responsibilities' => 'Mengawasi kebijakan direksi dan memberikan arahan strategis jangka panjang perusahaan.',
                    'sort_order' => 1,
                    'is_active' => true,
                ],
                [
                    'name' => 'Ir. Budi Santoso, M.T.',
                    'role' => 'Direktur Utama',
                    'division' => 'Direksi',
                    'level' => 2,
                    'parent_id' => 1,
                    'photo_path' => null,
                    'bio' => 'Memimpin visi operasional dan ekspansi bisnis PT. Karya Kembar Bersama secara komprehensif.',
                    'responsibilities' => 'Memimpin seluruh divisi operasional dan bertanggung jawab atas kinerja komersial perusahaan.',
                    'sort_order' => 1,
                    'is_active' => true,
                ],
                [
                    'name' => 'Hendra Wijaya, S.E., Ak.',
                    'role' => 'Direktur Keuangan & SDM',
                    'division' => 'Direksi',
                    'level' => 2,
                    'parent_id' => 2,
                    'photo_path' => null,
                    'bio' => 'Pakar dalam manajemen risiko keuangan, efisiensi anggaran, dan pengembangan SDM unggul.',
                    'responsibilities' => 'Mengendalikan alokasi anggaran perusahaan, manajemen kas, dan strategi pengembangan talenta.',
                    'sort_order' => 2,
                    'is_active' => true,
                ],
                [
                    'name' => 'Bambang Kusuma, S.T.',
                    'role' => 'General Manager Operasional',
                    'division' => 'Operasional',
                    'level' => 3,
                    'parent_id' => 2,
                    'photo_path' => null,
                    'bio' => 'Memiliki rekam jejak panjang dalam eksekusi proyek lapangan dan kendali mutu operasional.',
                    'responsibilities' => 'Mengkoordinasikan tim teknis dan memastikan standar K3LH serta SLA proyek terpenuhi.',
                    'sort_order' => 1,
                    'is_active' => true,
                ],
                [
                    'name' => 'Rina Kartika, S.E.',
                    'role' => 'Manager Keuangan & Akuntansi',
                    'division' => 'Keuangan',
                    'level' => 3,
                    'parent_id' => 3,
                    'photo_path' => null,
                    'bio' => 'Mengelola pelaporan keuangan, perpajakan, dan audit internal perusahaan secara transparan.',
                    'responsibilities' => 'Penyusunan laporan keuangan bulanan, manajemen payroll, dan kepatuhan regulasi pajak.',
                    'sort_order' => 1,
                    'is_active' => true,
                ],
                [
                    'name' => 'Dewi Anggraini, S.H., M.H.',
                    'role' => 'Manager HR & Legal',
                    'division' => 'HR & Legal',
                    'level' => 3,
                    'parent_id' => 3,
                    'photo_path' => null,
                    'bio' => 'Spesialis hukum korporasi, perizinan usaha, dan manajemen hubungan kepegawaian.',
                    'responsibilities' => 'Pengelolaan kontrak kerja, perizinan instansi, recruitment, dan legalitas korporasi.',
                    'sort_order' => 2,
                    'is_active' => true,
                ],
            ];

            foreach ($data as $item) {
                OrgStructure::create($item);
            }
        }
    }
}
