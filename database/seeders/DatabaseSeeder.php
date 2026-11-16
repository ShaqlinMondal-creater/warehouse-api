<?php

namespace Database\Seeders;

use App\Models\Bid;
use App\Models\BidWorkspace;
use App\Models\Plan;
use App\Models\PlanItem;
use App\Models\Setting;
use App\Models\User;
use App\Models\WorkProject;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $user = User::query()->updateOrCreate(
            ['email' => 'demo@shaqlin.warehouse'],
            [
                'name' => 'Shaqlin Mondal',
                'password' => Hash::make('demo1234'),
                'role' => 'owner',
                'avatar_initials' => 'SM',
            ],
        );

        // —— Portfolio (previous projects) ——
        $portfolio = [
            [
                'title' => 'MTC Document Extractor',
                'slug' => 'mtc-document-extractor',
                'summary' => 'Excel/Word/PDF extract with optional OpenAI JSON.',
                'description' => 'Reads files with PhpSpreadsheet/PhpWord; optional gpt-4o-mini for structured PO/QAP data.',
                'role' => 'Laravel developer',
                'stack' => ['Laravel', 'OpenAI', 'PhpSpreadsheet'],
                'platform_or_client' => 'Internal / client ops',
                'status' => 'done',
                'sort_order' => 1,
            ],
            [
                'title' => 'Freelancer Bid Warehouse (UI)',
                'slug' => 'bid-warehouse-ui',
                'summary' => 'React dashboard for bids, analytics, and public profile.',
                'description' => 'Vite + Tailwind demo UI with public page, login, and analytics mock.',
                'role' => 'Frontend',
                'stack' => ['React', 'Vite', 'Tailwind'],
                'platform_or_client' => 'Personal',
                'status' => 'ongoing',
                'sort_order' => 2,
            ],
            [
                'title' => 'Agency Retainer Portal',
                'slug' => 'agency-retainer-portal',
                'summary' => 'Demo previous project placeholder for portfolio.',
                'role' => 'Full-stack',
                'stack' => ['Laravel', 'Vue'],
                'platform_or_client' => 'Agency',
                'status' => 'done',
                'sort_order' => 3,
            ],
        ];

        foreach ($portfolio as $row) {
            WorkProject::query()->updateOrCreate(
                ['user_id' => $user->id, 'slug' => $row['slug']],
                array_merge($row, ['user_id' => $user->id, 'is_public' => true]),
            );
        }

        // —— Bid workspaces ——
        $freelancer = BidWorkspace::query()->updateOrCreate(
            ['user_id' => $user->id, 'slug' => 'freelancer'],
            [
                'name' => 'Freelancer Bids',
                'platform' => 'freelancer',
                'currency' => 'INR',
                'is_active' => true,
                'sort_order' => 1,
            ],
        );

        $upwork = BidWorkspace::query()->updateOrCreate(
            ['user_id' => $user->id, 'slug' => 'upwork'],
            [
                'name' => 'Upwork Pipeline',
                'platform' => 'upwork',
                'currency' => 'USD',
                'is_active' => true,
                'sort_order' => 2,
            ],
        );

        BidWorkspace::query()->updateOrCreate(
            ['user_id' => $user->id, 'slug' => 'other'],
            [
                'name' => 'Other Platforms',
                'platform' => 'other',
                'currency' => 'INR',
                'is_active' => true,
                'sort_order' => 3,
            ],
        );

        $demoBids = [
            [$freelancer, 'Website Redesign for SaaS Dashboard', 'TechNova Solutions', 42, 14200, 12000, 1, 'active'],
            [$freelancer, 'Mobile App UI/UX Design', 'BrightPath Labs', 28, 18500, 16000, 2, 'active'],
            [$freelancer, 'Custom WordPress Build', 'Northwind Retail', 35, 9800, 8500, 3, 'watching'],
            [$upwork, 'API Integration & Admin Panel', 'Orbit Pay', 19, 2200, 2050, 4, 'active'],
            [$upwork, 'Landing Page + SEO Pack', 'GreenLeaf Co', 51, 740, 680, 2, 'watching'],
        ];

        foreach ($demoBids as [$workspace, $name, $client, $total, $avg, $mine, $rank, $status]) {
            Bid::query()->updateOrCreate(
                [
                    'bid_workspace_id' => $workspace->id,
                    'project_name' => $name,
                ],
                [
                    'client_name' => $client,
                    'total_bids' => $total,
                    'average_bid' => $avg,
                    'average_bid_raw' => $workspace->currency === 'USD' ? '$'.$avg : '₹'.number_format($avg / 1000, 1).'k',
                    'my_bid' => $mine,
                    'my_bid_raw' => $workspace->currency === 'USD' ? '$'.$mine : '₹'.number_format($mine / 1000, 1).'k',
                    'bid_type' => 'fixed',
                    'my_rank' => $rank,
                    'status' => $status,
                    'source' => 'manual',
                    'last_synced_at' => now(),
                ],
            );
        }

        // —— Future planning ——
        $plan = Plan::query()->updateOrCreate(
            ['user_id' => $user->id, 'title' => 'Warehouse product roadmap'],
            [
                'description' => 'Phase plan for portfolio, bids analytics, and AI sync.',
                'status' => 'active',
                'target_date' => now()->addMonths(3)->toDateString(),
            ],
        );

        $items = [
            ['Wire React login to Sanctum', 40, 'doing', 'high', 1],
            ['Portfolio CRUD for previous projects', 10, 'todo', 'high', 2],
            ['Bids + multi-platform analytics API', 20, 'doing', 'high', 3],
            ['Planning progress UI', 0, 'todo', 'med', 4],
            ['Optional OpenAI screenshot extract', 0, 'todo', 'med', 5],
        ];

        foreach ($items as [$title, $progress, $status, $priority, $order]) {
            PlanItem::query()->updateOrCreate(
                ['plan_id' => $plan->id, 'title' => $title],
                [
                    'progress' => $progress,
                    'status' => $status,
                    'priority' => $priority,
                    'sort_order' => $order,
                ],
            );
        }

        // —— Config (AI + app) —— non-secret defaults; key stays in .env
        $settings = [
            ['ai', 'provider', 'openai', false],
            ['ai', 'model', 'gpt-4o-mini', false],
            ['ai', 'enabled', 'false', false],
            ['ai', 'vision_enabled', 'true', false],
            ['ai', 'max_tokens', '2000', false],
            ['app', 'name', 'Shaqlin Warehouse', false],
            ['app', 'default_currency', 'INR', false],
            ['app', 'timezone', 'Asia/Kolkata', false],
            ['app', 'public_profile_enabled', 'true', false],
            ['app', 'default_bid_workspace', 'freelancer', false],
        ];

        foreach ($settings as [$group, $key, $value, $secret]) {
            Setting::query()->updateOrCreate(
                ['user_id' => $user->id, 'group' => $group, 'key' => $key],
                ['value' => $value, 'is_secret' => $secret],
            );
        }
    }
}
