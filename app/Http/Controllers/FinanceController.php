<?php

namespace App\Http\Controllers;

use App\Models\LeagueNight;
use App\Models\Season;
use App\Models\Setting;
use App\Services\Finance;
use App\Services\League;
use Illuminate\View\View;

/** Public: the league's money, week by week. Recording payments is in the admin. */
class FinanceController extends Controller
{
    public function index(): View
    {
        $finance = $this->finance();

        $weeks = $finance->dueNights()->reverse()->values()->map(
            fn (LeagueNight $night) => ['night' => $night] + $finance->week($night)
        );

        return view('finance.index', [
            'finance' => $finance,
            'owing' => $finance->owing(),
            'weeks' => $weeks,
            'expenses' => $finance->league->season->expenses()->get(),
        ]);
    }

    public function week(int $week): View
    {
        $finance = $this->finance();
        $night = $finance->league->nights->firstWhere('week', $week);
        abort_unless($night && $finance->isDue($night), 404);

        return view('finance.week', [
            'finance' => $finance,
            'night' => $night,
            'week' => $finance->week($night),
        ]);
    }

    private function finance(): Finance
    {
        $season = Season::current();
        $league = $season ? new League($season, Setting::current()) : null;

        if (! $league || ! $league->isLive()) {
            // Not set up yet: the admin is told what's missing, everyone else sees the holding page
            if (auth()->check()) {
                redirect()->route('admin.dashboard')
                    ->withErrors(['finance' => 'Finance needs the league set up first: points, subs per night and saved fixtures.'])
                    ->throwResponse();
            }
            redirect()->route('home')->throwResponse();
        }

        return new Finance($league);
    }
}
