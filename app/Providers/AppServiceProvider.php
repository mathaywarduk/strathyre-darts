<?php

namespace App\Providers;

use App\Support\LeagueState;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        // Both layouts know how far set up the league is, so they only link to pages that work
        View::composer(['components.layouts.site', 'components.layouts.admin'], function ($view) {
            $view->with(LeagueState::flags());
        });

        if ($this->app->isProduction()) {
            URL::forceScheme('https');
        }
    }
}
