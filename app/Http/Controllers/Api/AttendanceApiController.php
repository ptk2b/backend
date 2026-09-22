<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AttendanceSummary;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class AttendanceApiController extends Controller
{
    /**
     * Get attendance matrix and KPI stats.
     */
    public function matrix(Request $request): JsonResponse
    {
        $year = (int) ($request->input('year') ?? 2026);

        // Ensure default seed data exists if table is empty for year 2026
        if ($year === 2026) {
            $count2026 = AttendanceSummary::where('year', 2026)->count();
            if ($count2026 === 0) {
                AttendanceSummary::seedInitial2026Data();
            }
        }

        // Determine which months to display
        // Mode options:
        // 1. 'full': 1 to 12
        // 2. 'range': from start_month to end_month (e.g. 5 to 12)
        // 3. 'custom': selected array of months
        $mode = $request->input('mode', 'all'); // 'all', 'range', 'custom'
        $selectedMonths = [];

        if ($mode === 'range') {
            $start = max(1, min(12, (int) ($request->input('start_month', 5))));
            $end = max(1, min(12, (int) ($request->input('end_month', 12))));
            if ($start > $end) {
                $temp = $start;
                $start = $end;
                $end = $temp;
            }
            for ($m = $start; $m <= $end; $m++) {
                $selectedMonths[] = $m;
            }
        } elseif ($mode === 'custom' && $request->has('months')) {
            $raw = $request->input('months');
            if (is_string($raw)) {
                $raw = explode(',', $raw);
            }
            foreach ((array) $raw as $m) {
                $val = (int) trim($m);
                if ($val >= 1 && $val <= 12) {
                    $selectedMonths[] = $val;
                }
            }
            sort($selectedMonths);
            if (empty($selectedMonths)) {
                $selectedMonths = range(1, 12);
            }
        } else {
            // Default: either range 5-12 or full 1-12. If specifically asked 'all', range 1..12
            // If none passed, default to all 12 months with clear labels
            $selectedMonths = range(1, 12);
        }

        // Fetch all records for the requested year
        $records = AttendanceSummary::where('year', $year)->get();

        // Index records by area_kerja, kategori, month
        $indexed = [];
        foreach ($records as $r) {
            $area = trim($r->area_kerja);
            $kat = strtoupper(trim($r->kategori));
            $indexed[$area][$kat][$r->month] = (int) $r->total_count;
        }

        // Build areas matrix
        $areasResult = [];
        $grandTotals = [
            'SAKIT' => ['monthly' => array_fill_keys($selectedMonths, 0), 'total' => 0],
            'IJIN'  => ['monthly' => array_fill_keys($selectedMonths, 0), 'total' => 0],
            'ALPA'  => ['monthly' => array_fill_keys($selectedMonths, 0), 'total' => 0],
            'ALL'   => ['monthly' => array_fill_keys($selectedMonths, 0), 'total' => 0],
        ];

        $allYearGrandTotals = [
            'SAKIT' => 0,
            'IJIN' => 0,
            'ALPA' => 0,
            'ALL' => 0,
        ];

        $areaTotalsForKpi = [];

        foreach (AttendanceSummary::STANDARD_AREAS as $areaName) {
            $areaRows = [];
            $subtotalMonthly = array_fill_keys($selectedMonths, 0);
            $subtotalAll = 0;
            $areaAllMonthsSum = 0;

            foreach (AttendanceSummary::CATEGORIES as $kategori) {
                $monthlyCounts = [];
                $rowTotal = 0;
                $rowAllYearTotal = 0;

                // Loop 1..12 for full year stats
                for ($m = 1; $m <= 12; $m++) {
                    $count = $indexed[$areaName][$kategori][$m] ?? 0;
                    $rowAllYearTotal += $count;
                    if (in_array($m, $selectedMonths)) {
                        $monthlyCounts[$m] = $count;
                        $rowTotal += $count;
                        $subtotalMonthly[$m] += $count;
                        $grandTotals[$kategori]['monthly'][$m] += $count;
                        $grandTotals['ALL']['monthly'][$m] += $count;
                    }
                }

                $subtotalAll += $rowTotal;
                $areaAllMonthsSum += $rowAllYearTotal;
                $grandTotals[$kategori]['total'] += $rowTotal;
                $grandTotals['ALL']['total'] += $rowTotal;

                $allYearGrandTotals[$kategori] += $rowAllYearTotal;
                $allYearGrandTotals['ALL'] += $rowAllYearTotal;

                $areaRows[] = [
                    'kategori' => $kategori,
                    'monthly_counts' => $monthlyCounts,
                    'total' => $rowTotal,
                    'full_year_total' => $rowAllYearTotal,
                ];
            }

            $areaTotalsForKpi[$areaName] = $areaAllMonthsSum;

            $areasResult[] = [
                'area_kerja' => $areaName,
                'rows' => $areaRows,
                'subtotal_monthly' => $subtotalMonthly,
                'subtotal_all' => $subtotalAll,
                'full_year_subtotal' => $areaAllMonthsSum,
            ];
        }

        // Find area with highest incidents
        arsort($areaTotalsForKpi);
        $highestAreaName = key($areaTotalsForKpi) ?: '-';
        $highestAreaCount = current($areaTotalsForKpi) ?: 0;

        // Formatted month list for headers
        $monthsHeader = [];
        foreach ($selectedMonths as $mNum) {
            $monthsHeader[] = [
                'month' => $mNum,
                'label' => AttendanceSummary::MONTH_LABELS[$mNum] ?? (string)$mNum,
            ];
        }

        $allMonthsList = [];
        foreach (range(1, 12) as $mNum) {
            $allMonthsList[] = [
                'month' => $mNum,
                'label' => AttendanceSummary::MONTH_LABELS[$mNum],
            ];
        }

        return response()->json([
            'status' => 'success',
            'year' => $year,
            'mode' => $mode,
            'months' => $monthsHeader,
            'all_months' => $allMonthsList,
            'areas' => $areasResult,
            'grand_totals' => [
                'sakit' => $grandTotals['SAKIT'],
                'ijin' => $grandTotals['IJIN'],
                'alpa' => $grandTotals['ALPA'],
                'all' => $grandTotals['ALL'],
            ],
            'kpi' => [
                'total_sakit' => $allYearGrandTotals['SAKIT'],
                'total_ijin' => $allYearGrandTotals['IJIN'],
                'total_alpa' => $allYearGrandTotals['ALPA'],
                'total_all' => $allYearGrandTotals['ALL'],
                'selected_period_total' => $grandTotals['ALL']['total'],
                'highest_area' => [
                    'name' => $highestAreaName,
                    'total' => $highestAreaCount,
                ],
            ],
        ]);
    }

    /**
     * Batch or single update attendance data.
     */
    public function update(Request $request): JsonResponse
    {
        $userId = $request->user() ? $request->user()->id : null;
        $year = (int) ($request->input('year') ?? 2026);

        // Check if updates array is passed
        if ($request->has('updates') && is_array($request->input('updates'))) {
            $updates = $request->input('updates');
            DB::beginTransaction();
            try {
                foreach ($updates as $item) {
                    $area = trim($item['area_kerja'] ?? '');
                    $kategori = strtoupper(trim($item['kategori'] ?? ''));
                    $month = (int) ($item['month'] ?? 0);
                    $count = max(0, (int) ($item['total_count'] ?? 0));

                    if ($area && $kategori && $month >= 1 && $month <= 12) {
                        AttendanceSummary::updateOrCreate(
                            [
                                'year' => $year,
                                'month' => $month,
                                'area_kerja' => $area,
                                'kategori' => $kategori,
                            ],
                            [
                                'total_count' => $count,
                                'updated_by' => $userId,
                            ]
                        );
                    }
                }
                DB::commit();
                return response()->json([
                    'status' => 'success',
                    'message' => 'Data absensi berhasil diperbarui.',
                ]);
            } catch (\Exception $e) {
                DB::rollBack();
                Log::error('Attendance update error: ' . $e->getMessage());
                return response()->json([
                    'status' => 'error',
                    'message' => 'Gagal memperbarui data absensi: ' . $e->getMessage(),
                ], 500);
            }
        }

        // Single cell update
        $validated = $request->validate([
            'area_kerja' => 'required|string',
            'kategori' => 'required|string|in:SAKIT,IJIN,ALPA,sakit,ijin,alpa',
            'month' => 'required|integer|min:1|max:12',
            'total_count' => 'required|integer|min:0',
        ]);

        $record = AttendanceSummary::updateOrCreate(
            [
                'year' => $year,
                'month' => (int) $validated['month'],
                'area_kerja' => trim($validated['area_kerja']),
                'kategori' => strtoupper(trim($validated['kategori'])),
            ],
            [
                'total_count' => (int) $validated['total_count'],
                'updated_by' => $userId,
            ]
        );

        return response()->json([
            'status' => 'success',
            'message' => 'Angka absensi berhasil disimpan.',
            'data' => $record,
        ]);
    }

    /**
     * Reset or seed initial 2026 attendance data from Excel reference.
     */
    public function seedInitial(Request $request): JsonResponse
    {
        AttendanceSummary::seedInitial2026Data();

        return response()->json([
            'status' => 'success',
            'message' => 'Data awal monitoring absensi tahun 2026 berhasil dimuat sesuai lembar kerja.',
        ]);
    }
}
