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
            ['section' => 'hero', 'content_key' => 'bgImage', 'lang' => 'id', 'content_value' => '/hero-bg.jpg', 'content_type' => 'text'],
            ['section' => 'hero', 'content_key' => 'bgImage', 'lang' => 'en', 'content_value' => '/hero-bg.jpg', 'content_type' => 'text'],
            ['section' => 'about', 'content_key' => 'image', 'lang' => 'id', 'content_value' => '/about-bg.jpg', 'content_type' => 'text'],
            ['section' => 'about', 'content_key' => 'image', 'lang' => 'en', 'content_value' => '/about-bg.jpg', 'content_type' => 'text'],
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
        SiteContent::whereIn('section', ['hero', 'about'])
            ->whereIn('content_key', ['bgImage', 'image'])
            ->delete();
    }
};
