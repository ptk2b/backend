<?php

namespace Database\Seeders;

use App\Models\Career;
use Illuminate\Database\Seeder;

class CareerSeeder extends Seeder
{
    public function run(): void
    {
        $careers = [
            ['title' => 'Mining Engineer', 'department' => 'Operasional', 'location' => 'Paser, Kaltim', 'type' => 'Full-time', 'is_urgent' => true],
            ['title' => 'HSE Officer', 'department' => 'Keselamatan', 'location' => 'Paser, Kaltim', 'type' => 'Full-time', 'is_urgent' => false],
            ['title' => 'Environmental Analyst', 'department' => 'Lingkungan', 'location' => 'Paser, Kaltim', 'type' => 'Full-time', 'is_urgent' => false],
            ['title' => 'Geologist', 'department' => 'Eksplorasi', 'location' => 'Paser, Kaltim', 'type' => 'Full-time', 'is_urgent' => true],
        ];

        foreach ($careers as $career) {
            Career::updateOrCreate(
                ['title' => $career['title']],
                $career
            );
        }
    }
}
