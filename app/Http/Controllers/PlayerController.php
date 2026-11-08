<?php

namespace App\Http\Controllers;

use App\Models\Player;
use App\Models\Season;
use App\Models\Setting;
use App\Models\Tournament;
use App\Services\Finance;
use App\Services\League;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class PlayerController extends Controller
{
    public function show(Request $request, Player $player): View|RedirectResponse
    {
        $season = Season::current();
        $league = $season ? new League($season, Setting::current()) : null;

        // Not set up yet, or not a player in this season: back to the league table
        if (! $league || ! $league->isLive() || ! $league->players->has($player->id)) {
            return redirect()->route('home');
        }

        // Where "back" goes: the finance pages, or a week's subs page, for the admin who came from there
        $from = (string) $request->query('from');
        $back = ['url' => Tournament::running() ? route('league') : route('home'), 'label' => 'Back to league table', 'section' => 'league'];
        if ($from === 'finance') {
            $back = ['url' => route('finance.index'), 'label' => 'Back to finance', 'section' => 'finance'];
        } elseif ($from === 'owed') {
            $back = ['url' => route('finance.owed'), 'label' => 'Back to subs owed', 'section' => 'finance'];
        } elseif (preg_match('/^week\.(\d+)$/', $from, $m)) {
            $back = ['url' => route('finance.week', $m[1]), 'label' => "Back to week {$m[1]} subs", 'section' => 'finance'];
        }

        $fixtures = $league->fixturesFor($player->id);

        return view('players.show', [
            'league' => $league,
            'player' => $player,
            'row' => $league->row($player->id),
            'leaders' => $league->leaders(),
            'back' => $back,
            'next' => $fixtures->first(fn ($f) => $league->isUpcoming($f)),
            'catchUps' => $fixtures->filter(fn ($f) => $league->isCatchUp($f))->values(),
            'upcoming' => $fixtures->filter(fn ($f) => $league->isUpcoming($f))->values(),
            'results' => $fixtures->filter(fn ($f) => $f->result)->reverse()->values(),
            'highestCheckout' => $league->highestCheckout($player->id),
            'holdsBestCheckout' => in_array($player->id, $league->bestCheckoutPlayerIds(), true),
            'subs' => (new Finance($league))->player($player->id),
            'subsUpdated' => $league->season->subs_updated_at,
        ]);
    }
}
