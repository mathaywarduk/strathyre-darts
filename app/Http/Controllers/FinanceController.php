<?php

namespace App\Http\Controllers;

use App\Models\LeagueNight;
use App\Models\Season;
use App\Models\Setting;
use App\Services\Finance;
use App\Services\League;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

/** Public: the league's money, week by week. Recording payments is in the admin. */
class FinanceController extends Controller
{
    /** The headline numbers, each linking to its detail page. */
    public function index(): View
    {
        return view('finance.index', ['finance' => $this->finance()]);
    }

    public function received(): View
    {
        $finance = $this->finance();

        return view('finance.received', [
            'finance' => $finance,
            'weeks' => $finance->dueNights()->reverse()->values()->map(
                fn (LeagueNight $night) => ['night' => $night] + $finance->week($night)
            ),
        ]);
    }

    public function owed(): View
    {
        $finance = $this->finance();

        return view('finance.owed', ['finance' => $finance, 'owing' => $finance->owing()]);
    }

    public function expenses(): View
    {
        $finance = $this->finance();

        return view('finance.expenses', [
            'finance' => $finance,
            'expenses' => $finance->league->season->expenses()->get(),
        ]);
    }

    public function week(int $week): View|RedirectResponse
    {
        $finance = $this->finance();
        $night = $finance->league->nights->firstWhere('week', $week);

        // A week that doesn't exist or hasn't come round yet: back to the finance page
        if (! $night || ! $finance->isDue($night)) {
            return redirect()->route('finance.index');
        }

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
