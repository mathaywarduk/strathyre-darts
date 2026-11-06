<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Season;
use App\Models\Setting;
use App\Models\SubsPayment;
use App\Services\Finance;
use App\Services\League;
use App\Support\Money;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class SubsController extends Controller
{
    /** Record a payment, see who owes what, and fix a mistaken payment. */
    public function index(Request $request): View|RedirectResponse
    {
        $finance = $this->finance();
        if (! $finance) {
            return redirect()->route('admin.dashboard')->withErrors(['subs' => 'Finish the setup first: the league needs points, subs and fixtures.']);
        }
        $owing = $finance->owing();

        return view('admin.subs', [
            'finance' => $finance,
            'owing' => $owing,
            'selected' => (int) $request->query('player', old('player_id')),
            'payments' => $finance->league->season->payments()->with('player')->orderByDesc('paid_on')->orderByDesc('id')->limit(30)->get(),
            'text' => $owing->map(fn ($r) => $r['player']->name.' '.Money::format($r['amount']).' ('.Finance::weeksText($r['weeks']).')')->implode("\n"),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $finance = $this->finance();
        abort_unless($finance, 404);

        $data = $request->validate([
            'player_id' => ['required', 'integer'],
            'amount' => ['required', 'regex:/^£?\d+(\.\d{1,2})?$/u'],
            'paid_on' => ['required', 'date_format:Y-m-d'],
        ], ['amount.regex' => 'Enter the amount as money, like 5 or 2.50.']);

        $player = $finance->league->players->get((int) $data['player_id']);
        abort_unless($player, 404);
        $pence = Money::parse($data['amount']);
        if (! $pence) {
            return back()->withInput()->withErrors(['amount' => 'The amount must be more than £0.']);
        }

        SubsPayment::create([
            'season_id' => $finance->league->season->id,
            'player_id' => $player->id,
            'amount_pence' => $pence,
            'paid_on' => $data['paid_on'],
        ]);

        $after = $this->finance();
        $owed = $after->owedBy($player->id);
        $credit = $after->creditOf($player->id);
        $now = $owed > 0 ? "Now owes {$this->money($owed)}." : ($credit > 0 ? "Now {$this->money($credit)} ahead." : 'Now paid up.');

        return redirect()->route('admin.subs')->with('status', "Recorded {$this->money($pence)} from {$player->name}. {$now}");
    }

    public function destroy(SubsPayment $payment): RedirectResponse
    {
        $payment->delete();

        return redirect()->route('admin.subs')->with('status', 'Payment deleted.');
    }

    private function finance(): ?Finance
    {
        $season = Season::current();
        $league = $season ? new League($season, Setting::current()) : null;

        return $league && $league->isLive() ? new Finance($league) : null;
    }

    private function money(int $pence): string
    {
        return Money::format($pence);
    }
}
