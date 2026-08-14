<?php

use Illuminate\Database\Migrations\Migration;
use App\Models\SiteContent;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        $newKeys = [
            ['section' => 'about', 'content_key' => 'badgeValue', 'lang' => 'id', 'content_value' => '20+', 'content_type' => 'text'],
            ['section' => 'about', 'content_key' => 'badgeValue', 'lang' => 'en', 'content_value' => '20+', 'content_type' => 'text'],
            ['section' => 'about', 'content_key' => 'badgeLabel', 'lang' => 'id', 'content_value' => 'Tahun Beroperasi', 'content_type' => 'text'],
            ['section' => 'about', 'content_key' => 'badgeLabel', 'lang' => 'en', 'content_value' => 'Years of Operation', 'content_type' => 'text'],
        ];

        foreach ($newKeys as $key) {
            SiteContent::updateOrCreate(
                [
                    'section' => $key['section'],
                    'content_key' => $key['content_key'],
                    'lang' => $key['lang'],
                ],
                [
                    'content_value' => $key['content_value'],
                    'content_type' => $key['content_type'],
                ]
            );
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        SiteContent::where('section', 'about')
            ->whereIn('content_key', ['badgeValue', 'badgeLabel'])
            ->delete();
    }
};
