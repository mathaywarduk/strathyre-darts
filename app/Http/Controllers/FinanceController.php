<?php

namespace App\Http\Controllers;

use App\Models\LeagueNight;
use App\Models\Player;
use App\Models\Season;
use App\Models\Setting;
use App\Models\SubsPayment;
use App\Services\Finance;
use App\Services\League;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

/** Admin-only (the routes sit behind the "hidden" middleware: a 404 unless logged in). */
class FinanceController extends Controller
{
    public function index(): View
    {
        $finance = $this->finance();

        $weeks = $finance->dueNights()->reverse()->values()->map(
            fn (LeagueNight $night) => ['night' => $night, 'upcoming' => false] + $finance->week($night)
        );

        // The next night, so subs can be taken before the day
        $next = $finance->league->nights->first(fn (LeagueNight $n) => ! $finance->isDue($n));
        if ($next) {
            $weeks->prepend(['night' => $next, 'upcoming' => true] + $finance->week($next));
        }

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
        $night = $this->night($finance, $week);

        return view('finance.week', [
            'finance' => $finance,
            'night' => $night,
            'week' => $finance->week($night),
        ]);
    }

    public function toggle(int $week, Player $player): RedirectResponse
    {
        $finance = $this->finance();
        $night = $this->night($finance, $week);
        abort_unless($finance->league->players->has($player->id), 404);

        $existing = SubsPayment::where(['player_id' => $player->id, 'league_night_id' => $night->id])->first();
        if ($existing) {
            $existing->delete();
        } else {
            SubsPayment::create([
                'season_id' => $night->season_id, 'player_id' => $player->id, 'league_night_id' => $night->id,
            ]);
        }

        return redirect()->to(route('finance.week', $week).'#p'.$player->id);
    }

    private function finance(): Finance
    {
        $season = Season::current();
        $league = $season ? new League($season, Setting::current()) : null;

        // Logged in but the league isn't set up yet: say so, rather than a 404
        if (! $league || ! $league->isLive()) {
            redirect()->route('admin.dashboard')
                ->withErrors(['finance' => 'Finance needs the league set up first: points, subs per night and saved fixtures.'])
                ->throwResponse();
        }

        return new Finance($league);
    }

    private function night(Finance $finance, int $week): LeagueNight
    {
        $night = $finance->league->nights->firstWhere('week', $week);
        abort_unless($night, 404);

        return $night;
    }
}
