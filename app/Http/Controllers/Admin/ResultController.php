<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Result;
use App\Models\Season;
use App\Models\Setting;
use App\Services\League;
use App\Services\ResultSubmission;
use App\Support\Dates;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class ResultController extends Controller
{
    public function index(Request $request): View|RedirectResponse
    {
        $league = $this->league();
        if (! $league->isLive()) {
            return redirect()->route('admin.dashboard')->withErrors(['league' => 'Finish the setup first: the league needs points, subs and fixtures.']);
        }

        return view('admin.results.index', [
            'league' => $league,
            'selected' => (int) $request->query('week', old('week', $league->currentNight()?->id ?? $league->nights->last()->id)),
        ]);
    }

    /** Show what would be saved. */
    public function preview(Request $request, ResultSubmission $submission): View|RedirectResponse
    {
        $league = $this->league();
        $data = $request->validate([
            'week' => ['required', 'integer'],
            'mode' => ['required', Rule::in(['results', 'none'])],
            'lines' => ['nullable', 'string', 'max:20000'],
        ]);
        $night = $league->nights->firstWhere('id', $data['week']);
        abort_unless($night, 404);

        if ($data['mode'] === 'none') {
            if ($night->isSubmitted()) {
                return redirect()->route('admin.results.index', ['week' => $night->id])
                    ->withErrors(['week' => "Week {$night->week} has already been submitted, so it can’t be marked as no results."]);
            }

            return view('admin.results.preview', [
                'league' => $league, 'night' => $night, 'mode' => 'none', 'lines' => '',
                'preview' => ['rows' => [], 'ok' => collect(), 'errorCount' => 0, 'postponed' => $league->fixturesForNight($night)->filter(fn ($f) => ! $f->result)],
            ]);
        }

        return view('admin.results.preview', [
            'league' => $league, 'night' => $night, 'mode' => 'results', 'lines' => (string) ($data['lines'] ?? ''),
            'preview' => $submission->preview($league, $night, (string) ($data['lines'] ?? '')),
        ]);
    }

    public function save(Request $request, ResultSubmission $submission): RedirectResponse
    {
        $league = $this->league();
        $data = $request->validate([
            'week' => ['required', 'integer'],
            'mode' => ['required', Rule::in(['results', 'none'])],
            'lines' => ['nullable', 'string', 'max:20000'],
        ]);
        $night = $league->nights->firstWhere('id', $data['week']);
        abort_unless($night, 404);

        if ($data['mode'] === 'none') {
            $submission->markNoResults($night);
            $message = "Week {$night->week} marked as no results. Its matches are now catch-ups.";
        } else {
            $submission->save($league, $night, (string) ($data['lines'] ?? ''));
            $message = "Week {$night->week} results saved.";
        }

        return redirect()->route('admin.dashboard')->with('status', $message)
            ->with('status_link', ['url' => route('home'), 'label' => 'View the league table']);
    }

    /** Back from the preview to the form, keeping what was typed. */
    public function back(Request $request): RedirectResponse
    {
        return redirect()->route('admin.results.index', ['week' => $request->input('week')])
            ->withInput(['lines' => $request->input('lines')]);
    }

    public function edit(Request $request, Result $result): View
    {
        $league = $this->league();
        $fixture = $league->fixtures->firstWhere('id', $result->fixture_id);
        abort_unless($fixture, 404);

        return view('admin.results.edit', [
            'league' => $league,
            'result' => $result,
            'fixture' => $fixture,
            'back' => Dates::safePath($request->query('back'), route('home', [], false)),
        ]);
    }

    public function update(Request $request, Result $result): RedirectResponse
    {
        $fixture = $result->fixture;
        $min = (int) config('league.min_checkout');
        $max = (int) config('league.max_checkout');
        $data = $request->validate([
            'winner_id' => ['required', Rule::in([$fixture->player1_id, $fixture->player2_id])],
            'checkout' => ['required', 'integer', "between:{$min},{$max}"],
            'back' => ['nullable', 'string'],
        ], [
            'checkout.between' => "The checkout must be a whole number from {$min} to {$max}.",
            'checkout.integer' => "The checkout must be a whole number from {$min} to {$max}.",
        ]);

        $result->update(['winner_id' => $data['winner_id'], 'checkout' => $data['checkout']]);

        return redirect(Dates::safePath($data['back'] ?? null, route('home', [], false)))->with('status', 'Result updated.');
    }

    /** Clear a result: the match goes back to being a catch-up (or an upcoming fixture). */
    public function destroy(Request $request, Result $result): RedirectResponse
    {
        $result->delete();

        return redirect(Dates::safePath($request->input('back'), route('home', [], false)))->with('status', 'Result cleared. The match is back in the fixtures.');
    }

    private function league(): League
    {
        $season = Season::current();
        abort_unless($season, 404);

        return new League($season, Setting::current());
    }
}
