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

        // Admin pages show how far through setup the league is, until it's live
        View::composer('components.layouts.admin', function ($view) {
            if (! auth()->check()) {
                return;
            }
            $steps = LeagueState::setup();
            $done = count(array_filter($steps, fn ($s) => $s[1]));
            $view->with([
                'setupTotal' => count($steps),
                'setupDone' => $done,
                'setupNext' => collect($steps)->first(fn ($s) => ! $s[1]),
            ]);
        });

        if ($this->app->isProduction()) {
            URL::forceScheme('https');
        }
    }
}
