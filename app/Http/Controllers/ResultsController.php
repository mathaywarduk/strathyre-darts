<?php

namespace App\Http\Controllers;

use App\Models\Season;
use App\Models\Setting;
use App\Services\League;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

/** Every result so far, grouped by the week of the fixture, newest week first. */
class ResultsController extends Controller
{
    public function index(): View|RedirectResponse
    {
        $season = Season::current();
        $league = $season ? new League($season, Setting::current()) : null;
        if (! $league || ! $league->isLive()) {
            return redirect()->route('home');
        }

        $weeks = $league->nights->reverse()->values()
            ->map(fn ($night) => [
                'night' => $night,
                'fixtures' => $league->fixturesForNight($night)->filter(fn ($f) => $f->result)->values(),
            ])
            ->filter(fn ($w) => $w['fixtures']->isNotEmpty())
            ->values();

        return view('results', [
            'league' => $league,
            'weeks' => $weeks,
            // The week whose night has come and gone (or it's late on the night) with nothing entered yet
            'pendingNight' => $league->resultsPending(now()) ? $league->currentNight() : null,
        ]);
    }
}
