<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Fixture;
use App\Models\LeagueNight;
use App\Models\Player;
use App\Models\Season;
use App\Models\Setting;
use App\Services\FixtureGenerator;
use App\Services\FixtureImport;
use App\Services\League;
use App\Services\SeasonCalendar;
use Carbon\CarbonImmutable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\View\View;

class FixtureController extends Controller
{
    private const DEFAULT_START = '2026-11-13';

    public function index(): View|RedirectResponse
    {
        $season = Season::current();
        if (! $season) {
            return redirect()->route('admin.season.index')->withErrors(['season' => 'Start a season first.']);
        }
        $league = new League($season, Setting::current());

        return view('admin.fixtures.index', [
            'season' => $season,
            'league' => $league,
            'locked' => $this->locked($season),
            'playerCount' => Player::count(),
            'form' => $this->formDefaults($season),
        ]);
    }

    /** Build a draft season and show it; nothing is saved yet. */
    public function generate(Request $request, FixtureGenerator $generator): View|RedirectResponse
    {
        $season = $this->unlockedSeason();
        $data = $request->validate([
            'start_on' => ['required', 'date_format:Y-m-d'],
            'max_weeks' => ['nullable', 'integer', 'min:1', 'max:60'],
            'off' => ['nullable', 'array'],
            'off.*' => ['date_format:Y-m-d'],
            'seed' => ['nullable', 'integer'],
        ]);
        $start = CarbonImmutable::parse($data['start_on']);
        if (! $start->isFriday()) {
            return back()->withInput()->withErrors(['start_on' => 'League nights are on Fridays, so the first night must be a Friday.']);
        }

        $players = Player::all();
        try {
            $plan = $generator->generate(
                $players->map(fn ($p) => ['id' => $p->id, 'strength' => $p->ranking])->all(),
                isset($data['max_weeks']) ? (int) $data['max_weeks'] : null,
                (int) ($data['seed'] ?? random_int(1, 2_000_000_000)),
            );
        } catch (\InvalidArgumentException $e) {
            return back()->withInput()->withErrors(['max_weeks' => $e->getMessage()]);
        }

        $off = array_values(array_unique($data['off'] ?? []));
        $dates = SeasonCalendar::dates($start, count($plan['weeks']), $off);

        $weeks = [];
        foreach ($plan['weeks'] as $i => $matches) {
            $weeks[] = ['week' => $i + 1, 'date' => $dates[$i]->toDateString(), 'matches' => $matches, 'heat' => $plan['heat'][$i]];
        }

        return $this->showPreview($season, [
            'source' => 'generated',
            'start_on' => $start->toDateString(),
            'max_weeks' => $data['max_weeks'] ?? null,
            'off' => $off,
            'weeks' => $weeks,
        ], $players);
    }

    public function manual(Request $request, FixtureImport $import): View|RedirectResponse
    {
        $season = $this->unlockedSeason();
        $request->validate(['csv' => ['nullable', 'file', 'max:512'], 'text' => ['nullable', 'string', 'max:100000']]);
        $text = $request->file('csv')?->get() ?? (string) $request->input('text');
        if (trim($text) === '') {
            return back()->withErrors(['text' => 'Choose a CSV file or paste the rows.']);
        }

        $players = Player::all();
        $result = $import->parse($text, $players);
        if ($result['errors']) {
            return back()->withInput(['text' => $text])->with('import_errors', $result['errors']);
        }

        return $this->showPreview($season, [
            'source' => 'manual',
            'start_on' => $result['weeks'][0]['date'],
            'max_weeks' => null,
            'off' => [],
            'weeks' => $result['weeks'],
        ], $players);
    }

    public function save(Request $request): RedirectResponse
    {
        $season = $this->unlockedSeason();
        $draft = session('fixture_draft');
        if (! $draft || $draft['token'] !== $request->input('token')) {
            return redirect()->route('admin.fixtures.index')->withErrors(['draft' => 'That draft has expired. Generate the fixtures again.']);
        }

        DB::transaction(function () use ($season, $draft) {
            DB::table('fixtures')->where('season_id', $season->id)->delete();
            DB::table('league_nights')->where('season_id', $season->id)->delete();

            $season->update([
                'starts_on' => $draft['start_on'],
                'max_weeks' => $draft['max_weeks'],
                'weeks_off' => $draft['off'],
            ]);

            foreach ($draft['weeks'] as $week) {
                $night = LeagueNight::create(['season_id' => $season->id, 'week' => $week['week'], 'date' => $week['date']]);
                foreach ($week['matches'] as [$a, $b]) {
                    Fixture::create([
                        'season_id' => $season->id, 'league_night_id' => $night->id,
                        'player1_id' => $a, 'player2_id' => $b, 'pair_key' => Fixture::pairKey($a, $b),
                    ]);
                }
            }
        });
        session()->forget('fixture_draft');

        return redirect()->route('admin.fixtures.index')->with('status', 'Fixtures saved.');
    }

    public function discard(): RedirectResponse
    {
        session()->forget('fixture_draft');

        return redirect()->route('admin.fixtures.index');
    }

    /** Move one league night, e.g. Christmas Day to the Thursday. */
    public function night(Request $request, LeagueNight $night): RedirectResponse
    {
        $data = $request->validate(['date' => ['required', 'date_format:Y-m-d']]);
        $night->update(['date' => $data['date']]);

        return redirect()->route('admin.fixtures.index')->with('status', "Week {$night->week} moved.");
    }

    private function showPreview(Season $season, array $draft, $players): View
    {
        $draft['token'] = Str::random(24);
        session(['fixture_draft' => $draft]);

        return view('admin.fixtures.preview', [
            'season' => $season,
            'draft' => $draft,
            'names' => $players->pluck('name', 'id'),
            'ranks' => $players->pluck('ranking', 'id'),
            'doubles' => collect($draft['weeks'])->sum(fn ($w) => $this->doublesIn($w['matches'])),
            'byes' => $this->byes($draft, $players),
        ]);
    }

    /** Players who'd play twice in a night */
    private function doublesIn(array $matches): int
    {
        $counts = array_count_values(array_merge(...array_map(fn ($m) => [$m[0], $m[1]], $matches)));

        return count(array_filter($counts, fn ($c) => $c > 1));
    }

    private function byes(array $draft, $players): array
    {
        $out = [];
        foreach ($draft['weeks'] as $w) {
            $playing = array_merge(...array_map(fn ($m) => [$m[0], $m[1]], $w['matches']));
            $out[$w['week']] = $players->reject(fn ($p) => in_array($p->id, $playing, true))->pluck('name')->all();
        }

        return $out;
    }

    private function formDefaults(Season $season): array
    {
        $start = $season->starts_on ?? CarbonImmutable::parse(self::DEFAULT_START);

        return [
            'start_on' => old('start_on', $start->toDateString()),
            'max_weeks' => old('max_weeks', $season->max_weeks),
            'off' => old('off', $season->weeks_off ?? []),
        ];
    }

    private function locked(Season $season): bool
    {
        return DB::table('results')->whereIn('fixture_id', DB::table('fixtures')->where('season_id', $season->id)->select('id'))->exists()
            || DB::table('subs_payments')->where('season_id', $season->id)->exists();
    }

    private function unlockedSeason(): Season
    {
        $season = Season::current();
        if (! $season) {
            redirect()->route('admin.season.index')->withErrors(['season' => 'Start a season first.'])->throwResponse();
        }
        if ($this->locked($season)) {
            back()->withErrors(['draft' => 'Results or subs are already recorded, so the fixtures are locked. Reset or delete the season to start again.'])->throwResponse();
        }

        return $season;
    }
}
