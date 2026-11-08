<?php

namespace App\Http\Controllers;

use App\Models\Season;
use App\Models\Setting;
use App\Services\League;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/** One week at a time: its results, and any fixtures not played yet. */
class ResultsController extends Controller
{
    public function index(Request $request): View|RedirectResponse
    {
        $season = Season::current();
        $league = $season ? new League($season, Setting::current()) : null;
        if (! $league || ! $league->isLive()) {
            return redirect()->route('home');
        }

        $nights = $league->nights;
        $night = $nights->firstWhere('week', (int) $request->query('week')) ?? $league->currentWeekNight(now());
        $i = $nights->search(fn ($n) => $n->id === $night->id);

        return view('results', [
            'league' => $league,
            'nights' => $nights,
            'night' => $night,
            'previous' => $nights[$i - 1] ?? null,
            'next' => $nights[$i + 1] ?? null,
            'fixtures' => $league->fixturesForNight($night),
            'byes' => $league->byes($night),
            // The week whose night has come and gone (or it's late on the night) with nothing entered yet
            'pendingNight' => $league->resultsPending(now()) ? $league->currentNight() : null,
        ]);
    }
}
