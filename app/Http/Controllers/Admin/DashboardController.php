<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Season;
use App\Models\Setting;
use App\Services\League;
use App\Support\LeagueState;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function index(): View
    {
        $season = Season::current();
        $settings = Setting::current();
        $league = $season ? new League($season, $settings) : null;

        $setup = LeagueState::setup();

        $night = $league?->currentNight();

        return view('admin.dashboard', [
            'season' => $season,
            'league' => $league,
            'setup' => $setup,
            'live' => (bool) $league?->isLive(),
            'night' => $night,
            'due' => $night && $night->date->lte(today()),
            'catchUps' => $league ? $league->catchUps()->count() : 0,
        ]);
    }
}
