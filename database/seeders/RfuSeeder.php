<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\RfuLocation;
use App\Models\RfuUnitRecord;

class RfuSeeder extends Seeder
{
    public function run(): void
    {
        $locations = [
            [
                'name' => 'Petrosea',
                'code' => 'petrosea',
                'rfu_count' => 45,
                'plan_count' => 60,
                'on_progress_count' => 4,
                'sort_order' => 1,
                'units' => [
                    [
                        'position' => 'Base 7',
                        'tanggal_masuk' => '2026-10-07',
                        'jam_masuk' => '08:50',
                        'dt_number' => 'DT 9111',
                        'driver_complaint' => 'TRANMISI BUNYI SEPIT 1-8 CEK KAN OLI TRANMISI NYA',
                        'estimation_type' => 'waiting_part',
                        'estimation_note' => 'Cek Oli & Part Transmisi',
                    ],
                    [
                        'position' => 'Base 5',
                        'tanggal_masuk' => '2026-10-12',
                        'jam_masuk' => '07:40',
                        'dt_number' => 'DT 9156',
                        'driver_complaint' => 'HOLLO TYRE NO 7-8 / 11-12 PECAH',
                        'estimation_type' => 'datetime',
                        'estimation_datetime' => '2026-10-12 14:00:00',
                        'estimation_note' => 'Ganti Tyre Baru',
                    ],
                    [
                        'position' => 'Base 8',
                        'tanggal_masuk' => '2026-10-11',
                        'jam_masuk' => '08:12',
                        'dt_number' => 'DT 9929',
                        'driver_complaint' => 'BERSIKAN EAFU / BOCOR ANGIN',
                        'estimation_type' => 'datetime',
                        'estimation_datetime' => '2026-10-12 15:30:00',
                        'estimation_note' => 'Pembersihan & Seal Angin',
                    ],
                    [
                        'position' => 'Base 4',
                        'tanggal_masuk' => '2026-10-12',
                        'jam_masuk' => '10:52',
                        'dt_number' => 'DT 9107',
                        'driver_complaint' => 'SEPRING PATAH NO 1 KANAN DEPAN',
                        'estimation_type' => 'waiting_part',
                        'estimation_note' => 'Waiting Spring Replacement',
                    ],
                ]
            ],
            [
                'name' => 'Pama',
                'code' => 'pama',
                'rfu_count' => 14,
                'plan_count' => 12,
                'on_progress_count' => 0,
                'sort_order' => 2,
                'units' => []
            ],
            [
                'name' => 'SIMS',
                'code' => 'sims',
                'rfu_count' => 5,
                'plan_count' => 12,
                'on_progress_count' => 4,
                'sort_order' => 3,
                'units' => [
                    [
                        'position' => 'Bay 2',
                        'tanggal_masuk' => '2026-10-12',
                        'jam_masuk' => '11:18',
                        'dt_number' => 'DT 7734',
                        'driver_complaint' => 'STABILIZER SEBLAH KIRI KOCAK BELAKANG, HOLO TAYER 5-6 , SPEED 5-4 SERING NETRAL, GEAR SHIFT',
                        'estimation_type' => 'overhaul',
                        'estimation_note' => 'Cek Stabilizer & Gear Shift',
                    ],
                    [
                        'position' => 'Lapangan',
                        'tanggal_masuk' => '2026-10-11',
                        'jam_masuk' => '16:25',
                        'dt_number' => 'DT 7747',
                        'driver_complaint' => 'ADA KEBOCORAN HOUSE RADIATOR',
                        'estimation_type' => 'datetime',
                        'estimation_datetime' => '2026-10-12 16:00:00',
                        'estimation_note' => 'Ganti Hose Radiator',
                    ],
                    [
                        'position' => 'Bay 6',
                        'tanggal_masuk' => '2026-10-12',
                        'jam_masuk' => '08:19',
                        'dt_number' => 'DT 7746',
                        'driver_complaint' => 'low power',
                        'estimation_type' => 'datetime',
                        'estimation_datetime' => '2026-10-12 17:00:00',
                        'estimation_note' => 'Cek Fuel Filter & Injector',
                    ],
                    [
                        'position' => 'Bay 4',
                        'tanggal_masuk' => '2026-10-12',
                        'jam_masuk' => '07:09',
                        'dt_number' => 'DT 7743',
                        'driver_complaint' => 'REM LOSS',
                        'estimation_type' => 'waiting_part',
                        'estimation_note' => 'Waiting Brake Valve Part',
                    ],
                ]
            ],
            [
                'name' => 'S. Paku',
                'code' => 'spaku',
                'rfu_count' => 10,
                'plan_count' => 10,
                'on_progress_count' => 2,
                'sort_order' => 4,
                'units' => [
                    [
                        'position' => 'Bay 5',
                        'tanggal_masuk' => '2026-09-22',
                        'jam_masuk' => '16:40',
                        'dt_number' => 'DT 742',
                        'driver_complaint' => 'MATI MENDADAK',
                        'estimation_type' => 'waiting_part',
                        'estimation_note' => 'Cek ECU / Electrical',
                    ],
                    [
                        'position' => 'Lapangan',
                        'tanggal_masuk' => '2026-09-25',
                        'jam_masuk' => '07:39',
                        'dt_number' => 'DT 750',
                        'driver_complaint' => 'ENGINE NOISE DI PIT STOP SMC',
                        'estimation_type' => 'overhaul',
                        'estimation_note' => 'Inspeksi Mesin Pit Stop',
                    ],
                ]
            ],
        ];

        foreach ($locations as $locData) {
            $units = $locData['units'];
            unset($locData['units']);

            $location = RfuLocation::updateOrCreate(
                ['code' => $locData['code']],
                $locData
            );

            // Re-seed units if empty
            if ($location->unitRecords()->count() === 0) {
                foreach ($units as $u) {
                    $u['location_id'] = $location->id;
                    $u['status'] = 'ON_PROGRESS';
                    RfuUnitRecord::create($u);
                }
            }

            // Sync on_progress_count
            $actualOnProgress = $location->unitRecords()->where('status', 'ON_PROGRESS')->count();
            if ($actualOnProgress > 0) {
                $location->update(['on_progress_count' => $actualOnProgress]);
            }
        }
    }
}
