<?php

namespace Database\Seeders;

use App\Models\PortfolioProfile;
use App\Models\User;
use App\Models\WorkProject;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class PortfolioSeeder extends Seeder
{
    public function run(): void
    {
        $user = User::query()->orderBy('id')->first();

        if (! $user) {
            $this->command?->warn('No user found. Log in / create a user first, then re-run: php artisan db:seed --class=PortfolioSeeder');

            return;
        }

        PortfolioProfile::query()->updateOrCreate(
            ['user_id' => $user->id],
            [
                'headline' => 'Shaqlin Mondal — PHP Laravel Developer',
                'tagline' => 'Laravel and PHP applications, the MySQL behind them, and the Tailwind interfaces people actually use.',
                'about' => 'I came into this work because solving a stubborn problem feels better than walking past it. With 2.5+ years in web development, I want a team where the work stays demanding and the learning does not flatten out. Day to day that is Laravel and PHP, React, MySQL, REST APIs, admin panels, and SaaS products with subscription payments.',
                'location' => 'Kolkata, West Bengal',
                'email' => 'shaqlinmondal8905@gmail.com',
                'phone' => '+91 8597148785',
                'current_role' => 'PHP Developer',
                'current_company' => 'Dotcom Solutions',
                'portfolio_url' => 'https://shaqlin-portfolio-26.vercel.app/',
                'stats' => [
                    'projects_built' => 25,
                    'years' => 2.5,
                    'companies' => 4,
                ],
                'services' => [
                    ['title' => 'Stores that take payments', 'detail' => 'Laravel stores with Razorpay payments. Liwaas, Haneri, Stockout.'],
                    ['title' => 'Company product sites', 'detail' => 'Product catalogue websites for businesses.'],
                    ['title' => 'REST APIs', 'detail' => 'Laravel endpoints between the front end and the back end.'],
                    ['title' => 'MySQL schemas', 'detail' => 'Database design, plus Excel and CSV import and export.'],
                    ['title' => 'Institution portals', 'detail' => 'Admin panel, student profiles, report cards, admit cards, payment setup.'],
                    ['title' => 'Company sites', 'detail' => 'Company websites with services and contact pages.'],
                    ['title' => 'Responsive interfaces', 'detail' => 'Layouts in Tailwind CSS that work from phone to desktop.'],
                    ['title' => 'SaaS and admin panels', 'detail' => 'Quotation, sales order, and invoice panels, plus SaaS products.'],
                ],
                'experience' => [
                    [
                        'title' => 'PHP Developer',
                        'company' => 'Dotcom Solutions',
                        'location' => 'Kolkata',
                        'period' => 'July 2024 — Present',
                        'current' => true,
                        'bullets' => [
                            'Building Laravel applications that stay maintainable as they grow.',
                            'Connecting front ends and back ends with REST APIs.',
                            'Designing MySQL schemas and keeping queries fast.',
                            'Building Laravel e-commerce stores with Razorpay payments.',
                        ],
                    ],
                    [
                        'title' => 'Industrial Internship · PHP & WordPress',
                        'company' => 'Euphoria GenX',
                        'location' => 'Kolkata',
                        'period' => 'September 2023 — April 2024',
                        'current' => false,
                        'bullets' => [
                            'Learned core PHP, including functions and OOP.',
                            'Customized WordPress themes and plugins.',
                        ],
                    ],
                    [
                        'title' => 'Intern · Back End Developer',
                        'company' => 'The Bengal Studio',
                        'location' => 'Kolkata',
                        'period' => 'August 2023 — October 2023',
                        'current' => false,
                        'bullets' => [
                            'PHP backend developer intern on a real project.',
                            'Used PHP and MySQL with phpMyAdmin.',
                        ],
                    ],
                    [
                        'title' => 'Intern · MEAN Stack Developer',
                        'company' => 'BASSETTI ITES PVT. LTD',
                        'location' => 'Kolkata',
                        'period' => 'September 2022 — January 2023',
                        'current' => false,
                        'bullets' => [
                            'MEAN stack: MongoDB, Express, Angular, Node.js.',
                            'Built CRUD applications with Node.js and Express.',
                        ],
                    ],
                ],
                'skills' => [
                    'languages' => [
                        ['name' => 'PHP', 'level' => 'Strong'],
                        ['name' => 'JavaScript', 'level' => 'Good'],
                        ['name' => 'TypeScript', 'level' => 'Intermediate'],
                        ['name' => 'HTML / CSS', 'level' => 'Strong'],
                        ['name' => 'MySQL', 'level' => 'Strong'],
                    ],
                    'frameworks' => [
                        ['name' => 'Laravel', 'level' => 'Strong'],
                        ['name' => 'React', 'level' => 'Good'],
                        ['name' => 'Next.js', 'level' => 'Intermediate'],
                        ['name' => 'Tailwind CSS', 'level' => 'Good'],
                        ['name' => 'Node.js / Express', 'level' => 'Intermediate'],
                        ['name' => 'WordPress / WooCommerce', 'level' => 'Basic'],
                    ],
                    'tools' => [
                        ['name' => 'REST APIs', 'level' => 'Strong'],
                        ['name' => 'Git / GitHub', 'level' => 'Good'],
                        ['name' => 'Razorpay', 'level' => 'Good'],
                        ['name' => 'cPanel / Plesk', 'level' => 'Good'],
                    ],
                ],
                'education' => [
                    [
                        'title' => 'B.Tech, Electronics & Communication Engineering',
                        'school' => 'Maulana Abul Kalam Azad University of Technology, GMIT, Kolkata',
                        'detail' => 'CGPA 8.48',
                        'period' => '2019 — 2023',
                    ],
                    [
                        'title' => 'Higher Secondary',
                        'school' => 'Dighirparh High Madrasah, Galsi, Burdwan',
                        'period' => '2017 — 2019',
                    ],
                    [
                        'title' => 'Secondary',
                        'school' => 'Memari V.M Institution, Memari, Burdwan',
                        'period' => '2013 — 2017',
                    ],
                ],
                'social' => [
                    'github' => 'https://github.com/',
                    'portfolio' => 'https://shaqlin-portfolio-26.vercel.app/',
                ],
                'is_public' => true,
            ],
        );

        $projects = [
            ['title' => 'Liwaas', 'summary' => 'Fashion e-commerce with Razorpay, shipping, and admin panel.', 'url' => 'https://liwaas.com', 'stack' => ['Laravel', 'React', 'MySQL', 'Razorpay'], 'meta' => ['category' => 'E-commerce', 'index' => 1]],
            ['title' => 'Haneri', 'summary' => 'E-commerce store built in Laravel. Backend and part of frontend.', 'url' => 'https://haneri.com', 'stack' => ['Laravel', 'MySQL'], 'meta' => ['category' => 'E-commerce', 'index' => 2]],
            ['title' => 'Stockout', 'summary' => 'Multi-vendor marketplace.', 'url' => 'https://stockoutindia.com', 'stack' => ['Laravel', 'REST API', 'Razorpay'], 'meta' => ['category' => 'E-commerce', 'index' => 3]],
            ['title' => 'Anhussanally', 'summary' => 'Product catalogue website for a business.', 'url' => 'https://anhussunally.com', 'stack' => ['PHP'], 'meta' => ['category' => 'WooCommerce', 'index' => 4]],
            ['title' => 'Johar Traders', 'summary' => 'Product catalogue website for a business.', 'url' => null, 'stack' => ['PHP'], 'status' => 'paused', 'meta' => ['category' => 'WooCommerce', 'index' => 5, 'site_status' => 'Offline']],
            ['title' => 'Sakberally', 'summary' => 'Product catalogue website for a business.', 'url' => null, 'stack' => ['PHP'], 'status' => 'paused', 'meta' => ['category' => 'WooCommerce', 'index' => 6, 'site_status' => 'Offline']],
            ['title' => 'Frosty Business', 'summary' => 'Product catalogue website for a business.', 'url' => 'https://frostybusiness.com', 'stack' => ['PHP'], 'meta' => ['category' => 'WooCommerce', 'index' => 7]],
            ['title' => 'Saifee School', 'summary' => 'Institution portal with admin, students, report cards, admit cards, payments, admission.', 'url' => 'https://saifeeschool.in', 'stack' => ['Laravel', 'MySQL'], 'meta' => ['category' => 'Institution portal', 'index' => 8]],
            ['title' => 'Raj Interiors', 'summary' => 'Company site with services and contact flows.', 'url' => null, 'stack' => ['Tailwind', 'PHP'], 'status' => 'paused', 'meta' => ['category' => 'Company site', 'index' => 9, 'site_status' => 'Offline']],
            ['title' => 'Safe Technical India', 'summary' => 'WordPress company site with services and contact flows.', 'url' => 'https://safetechnicalindia.in', 'stack' => ['WordPress'], 'meta' => ['category' => 'Company site', 'index' => 10]],
            ['title' => 'Safe Technical', 'summary' => 'WordPress company site with services and contact flows.', 'url' => null, 'stack' => ['WordPress'], 'status' => 'paused', 'meta' => ['category' => 'Company site', 'index' => 11, 'site_status' => 'Offline']],
            ['title' => 'Fluidtech', 'summary' => 'WordPress company site with services and contact flows.', 'url' => 'https://fluidtech.co', 'stack' => ['WordPress'], 'meta' => ['category' => 'Company site', 'index' => 12]],
            ['title' => 'Alamdar International', 'summary' => 'Product catalogue website for a business.', 'url' => 'https://alamdarinternational.com', 'stack' => ['PHP'], 'meta' => ['category' => 'WooCommerce', 'index' => 13]],
            ['title' => 'Supersteel', 'summary' => 'Product catalogue website for a power tools brand.', 'url' => 'https://supersteel.in', 'stack' => ['PHP'], 'meta' => ['category' => 'WooCommerce', 'index' => 14]],
            ['title' => 'Taraktube', 'summary' => 'Company website.', 'url' => 'https://taraktubeco.com', 'stack' => ['PHP'], 'meta' => ['category' => 'Company site', 'index' => 15]],
            ['title' => 'Topgrip', 'summary' => 'Company website with services and contact pages.', 'url' => 'https://topgrip.co.in', 'stack' => ['PHP'], 'meta' => ['category' => 'Company site', 'index' => 16]],
            ['title' => 'TBL Industrial', 'summary' => 'Company website with services and contact pages.', 'url' => 'https://tblindustrial.com', 'stack' => ['PHP'], 'meta' => ['category' => 'Company site', 'index' => 17]],
            ['title' => 'Smart Quote', 'summary' => 'SaaS quotation management with admin/seller panels and Razorpay subscriptions.', 'url' => 'https://smart-quote-three.vercel.app', 'stack' => ['React', 'Laravel', 'MySQL', 'Razorpay'], 'meta' => ['category' => 'SaaS', 'index' => 18]],
            ['title' => 'BhuriBhoj', 'summary' => 'SaaS mess management with role panels and subscriptions.', 'url' => 'https://bhuribhoj-v1.vercel.app', 'stack' => ['React', 'Laravel', 'MySQL', 'Razorpay'], 'meta' => ['category' => 'SaaS', 'index' => 19]],
            ['title' => 'Business admin panels', 'summary' => 'Private admin panels for quotation, sales order, and invoice generation.', 'url' => null, 'stack' => ['React', 'Laravel', 'MySQL'], 'meta' => ['category' => 'Private', 'index' => 20]],
            ['title' => 'Northline', 'summary' => 'Personal task management system.', 'url' => null, 'stack' => ['React', 'Node', 'Express', 'MongoDB'], 'meta' => ['category' => 'Private', 'index' => 21]],
        ];

        foreach ($projects as $i => $row) {
            $slug = Str::slug($row['title']);
            WorkProject::query()->updateOrCreate(
                [
                    'user_id' => $user->id,
                    'slug' => $slug,
                ],
                [
                    'title' => $row['title'],
                    'summary' => $row['summary'],
                    'description' => $row['summary'],
                    'stack' => $row['stack'],
                    'status' => $row['status'] ?? 'done',
                    'url' => $row['url'],
                    'sort_order' => $i + 1,
                    'is_public' => true,
                    'meta' => $row['meta'],
                ],
            );
        }

        $this->command?->info("Portfolio seeded for user #{$user->id} ({$user->email}).");
    }
}
