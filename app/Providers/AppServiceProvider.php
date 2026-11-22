<?php

namespace App\Providers;

use App\Models\PortfolioProfile;
use App\Models\WorkProject;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Relation::enforceMorphMap([
            'portfolio_profile' => PortfolioProfile::class,
            'work_project' => WorkProject::class,
        ]);
    }
}
