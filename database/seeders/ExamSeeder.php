<?php

namespace Database\Seeders;

use App\Models\ExamSet;
use Illuminate\Database\Seeder;

class ExamSeeder extends Seeder
{
    public function run(): void
    {
        $clerkship = ExamSet::query()->updateOrCreate(
            ['slug' => 'clerkship-2026'],
            [
                'title' => 'Clerkship 2026',
                'year' => 2026,
                'is_enabled' => true,
                'sort_order' => 1,
            ],
        );

        $clerkship->subjects()->updateOrCreate(
            ['slug' => 'general-studies'],
            [
                'title' => 'General Studies',
                'is_enabled' => true,
                'sort_order' => 1,
            ],
        );

        // Placeholders for later subject zips
        $clerkship->subjects()->updateOrCreate(
            ['slug' => 'mathematics'],
            [
                'title' => 'Mathematics',
                'is_enabled' => false,
                'sort_order' => 2,
            ],
        );
        $clerkship->subjects()->updateOrCreate(
            ['slug' => 'english'],
            [
                'title' => 'English',
                'is_enabled' => false,
                'sort_order' => 3,
            ],
        );

        ExamSet::query()->updateOrCreate(
            ['slug' => 'ido-2026'],
            [
                'title' => 'IDO 2026',
                'year' => 2026,
                'is_enabled' => true,
                'sort_order' => 2,
            ],
        );
    }
}
