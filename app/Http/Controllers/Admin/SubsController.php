<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Season;
use App\Models\Setting;
use App\Services\Finance;
use App\Services\League;
use App\Support\Money;
use Illuminate\View\View;

class SubsController extends Controller
{
    /** A quick report on who owes subs, with text to paste into a message. */
    public function index(): View
    {
        $season = Season::current();
        abort_unless($season, 404);
        $finance = new Finance(new League($season, Setting::current()));
        $owing = $finance->owing();

        return view('admin.subs', [
            'finance' => $finance,
            'owing' => $owing,
            'text' => $owing->map(fn ($r) => $r['player']->name.' '.Money::format($r['amount']).' ('.Finance::weeksText($r['weeks']).')')->implode("\n"),
        ]);
    }
}
