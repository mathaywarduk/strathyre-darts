<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Season;
use App\Models\Setting;
use App\Services\League;
use App\Services\SeasonManager;
use App\Support\Money;
use Carbon\CarbonImmutable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class SeasonController extends Controller
{
    public function index(): View
    {
        $current = Season::current();

        return view('admin.season', [
            'current' => $current,
            'league' => $current ? new League($current, Setting::current()) : null,
            'archived' => Season::where('status', 'archived')->orderByDesc('starts_on')->get(),
            'suggested' => ($current?->starts_on ?? CarbonImmutable::parse('2026-11-13'))->addYear()->next('friday')->toDateString(),
        ]);
    }

    /** Money already in the pot when the season began, e.g. left over from last year. */
    public function balance(Request $request): RedirectResponse
    {
        $current = Season::current();
        abort_unless($current, 404);

        $data = $request->validate(['balance' => ['required', 'regex:/^-?£?\d+(\.\d{1,2})?$|^£-?\d+(\.\d{1,2})?$/u']], [
            'balance.regex' => 'Enter the starting balance as money, like 120 or 85.50.',
            'balance.required' => 'Enter the starting balance, or 0 if there is none.',
        ]);

        $current->update(['opening_pence' => Money::parseSigned($data['balance'])]);

        return redirect()->route('admin.season.index')->with('status', 'Starting balance saved: '.Money::format($current->opening_pence).'.');
    }

    /** Start the first season, or the next one when none is current. */
    public function start(Request $request): RedirectResponse
    {
        abort_if(Season::current(), 422, 'A season is already running.');
        $date = $this->friday($request);
        Season::create(['starts_on' => $date, 'status' => 'current']);

        return redirect()->route('admin.fixtures.index')->with('status', 'Season started. Now set up its fixtures.');
    }

    public function reset(Request $request, SeasonManager $manager): RedirectResponse
    {
        $current = Season::current();
        abort_unless($current, 404);
        $request->validate(['confirm' => ['required', 'in:RESET']], ['confirm.in' => 'Type RESET to confirm.']);
        $date = $this->friday($request);

        $manager->reset($current, $date);

        return redirect()->route('admin.fixtures.index')->with('status', 'Season archived. Set up the new season’s fixtures.');
    }

    /** Hard delete: gone for good, for clearing out test data. */
    public function destroy(Request $request, Season $season, SeasonManager $manager): RedirectResponse
    {
        $request->validate(['confirm' => ['required', 'in:DELETE']], ['confirm.in' => 'Type DELETE to confirm.']);
        $manager->delete($season);

        return redirect()->route('admin.season.index')->with('status', 'Season deleted permanently.');
    }

    private function friday(Request $request): CarbonImmutable
    {
        $data = $request->validate(['starts_on' => ['required', 'date_format:Y-m-d']]);
        $date = CarbonImmutable::parse($data['starts_on']);
        if (! $date->isFriday()) {
            back()->withInput()->withErrors(['starts_on' => 'League nights are on Fridays, so the start date must be a Friday.'])->throwResponse();
        }

        return $date;
    }
}
