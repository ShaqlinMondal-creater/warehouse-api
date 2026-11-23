<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * No demo seed — create data via API only.
     */
    public function run(): void
    {
        $this->call(PortfolioSeeder::class);
    }
}
