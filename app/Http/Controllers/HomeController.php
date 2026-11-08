<?php

namespace App\Http\Controllers;

use App\Models\Season;
use App\Models\Setting;
use App\Models\Tournament;
use App\Services\League;
use App\Services\Tournament\Bracket;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class HomeController extends Controller
{
    /** While a doubles tournament is running the home page is the tournament. */
    public function index(): View
    {
        if ($tournament = Tournament::running()) {
            return view('tournament.public', ['tournament' => $tournament, 'bracket' => new Bracket($tournament)]);
        }

        return $this->leaguePage();
    }

    /** The league page, always. It only has its own address while the tournament has taken over the home page. */
    public function league(): View|RedirectResponse
    {
        return Tournament::running() ? $this->leaguePage() : redirect()->route('home');
    }

    private function leaguePage(): View
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
