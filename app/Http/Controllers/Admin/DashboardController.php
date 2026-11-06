<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Player;
use App\Models\Season;
use App\Models\Setting;
use App\Services\League;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function index(): View
    {
        $season = Season::current();
        $settings = Setting::current();
        $league = $season ? new League($season, $settings) : null;
        $players = Player::count();

        $setup = [
            ['Upload the players', $players >= 2, route('admin.players.index'), $players.' players'],
            ['Set points and subs', $settings->isComplete(), route('admin.settings.edit'), $settings->isComplete() ? null : 'Still to set: '.implode(', ', $settings->missing())],
            ['Start a season', (bool) $season, route('admin.season.index'), null],
            ['Save the fixtures', (bool) $league?->hasFixtures(), route('admin.fixtures.index'), null],
        ];

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
