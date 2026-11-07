<?php

namespace App\Http\Controllers;

use App\Models\Season;
use App\Models\Setting;
use App\Services\League;
use Illuminate\View\View;

class HomeController extends Controller
{
    public function index(): View
    {
        $season = Season::current();
        $settings = Setting::current();
        $league = $season ? new League($season, $settings) : null;

        if (! $league || ! $league->isLive()) {
            return view('holding', [
                'missing' => array_filter([
                    ...array_map(fn ($m) => 'Set '.$m, $settings->missing()),
                    $league && $league->hasFixtures() ? null : 'Save the fixtures',
                ]),
                'hasArchive' => Season::where('status', 'archived')->exists(),
            ]);
        }

        $night = $league->currentNight();
        $catchUps = $league->catchUps();

        return view('home', [
            'league' => $league,
            'table' => $league->table(),
            'leaders' => $league->leaders(),
            'bestCheckout' => $league->bestCheckout(),
            'bestCheckoutIds' => $league->bestCheckoutPlayerIds(),
            'night' => $night,
            'pending' => $league->resultsPending(now()),
            'nextFixtures' => $night ? $league->fixturesForNight($night) : collect(),
            'byes' => $night ? $league->byes($night) : collect(),
            'catchUps' => $catchUps,
            'hasArchive' => Season::where('status', 'archived')->exists(),
        ]);
    }
}
