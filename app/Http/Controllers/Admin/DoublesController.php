<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Player;
use App\Models\Tournament;
use App\Models\TournamentMatch;
use App\Services\Tournament\Bracket;
use App\Services\Tournament\TournamentException;
use App\Services\Tournament\TournamentService;
use App\Support\Dates;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/** The yearly doubles tournament: pick who's playing, draw it, score the matches, finish. */
class DoublesController extends Controller
{
    public function __construct(private TournamentService $tournaments) {}

    public function index(): View
    {
        $tournament = Tournament::running();

        return view('admin.doubles.index', [
            'tournament' => $tournament,
            'bracket' => $tournament ? new Bracket($tournament) : null,
            'past' => Tournament::query()->where('status', 'ended')->orderByDesc('ended_at')->orderByDesc('id')->get()
                ->map(fn (Tournament $t) => ['tournament' => $t, 'champions' => $t->champions()]),
        ]);
    }

    public function create(): View|RedirectResponse
    {
        if (Tournament::running()) {
            return redirect()->route('admin.doubles.index');
        }

        $players = Player::all()->sortBy(fn ($p) => mb_strtolower($p->name))->values();
        $fresh = ! session()->hasOldInput();

        // Last year's champions are filled in for you, and can be changed, replaced or cleared
        $champions = $fresh
            ? array_pad(Tournament::lastChampionNames(), 2, '')
            : array_pad(array_values((array) old('champions', [])), 2, '');
        $championKeys = array_map('mb_strtolower', array_filter($champions));

        // Champions who are in the league start ticked; any others start in the "other players" box
        $ticked = $players->filter(fn ($p) => in_array(mb_strtolower($p->name), $championKeys, true))->pluck('id')->all();
        $leagueKeys = $players->map(fn ($p) => mb_strtolower($p->name))->all();
        $outsiders = array_values(array_filter(array_filter($champions), fn ($n) => ! in_array(mb_strtolower($n), $leagueKeys, true)));

        return view('admin.doubles.new', [
            'players' => $players,
            'championKeys' => $championKeys,
            'champions' => $champions,
            'ticked' => $fresh ? $ticked : array_map('intval', (array) old('players', [])),
            'extras' => $fresh ? implode(', ', $outsiders) : old('extras', ''),
            'name' => old('name', 'Doubles '.today()->year),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        if (Tournament::running()) {
            return redirect()->route('admin.doubles.index')->withErrors(['tournament' => 'A tournament is already running.']);
        }

        $data = $request->validate([
            'name' => ['required', 'string', 'max:80'],
            'players' => ['nullable', 'array'],
            'players.*' => ['integer', 'exists:players,id'],
            'extras' => ['nullable', 'string', 'max:2000'],
            'champions' => ['nullable', 'array', 'max:2'],
            'champions.*' => ['nullable', 'string', 'max:60'],
        ]);

        $league = Player::whereIn('id', $data['players'] ?? [])->get();
        $leagueKeys = Player::pluck('name')->map(fn ($n) => mb_strtolower($n))->all();
        $extras = $this->names($data['extras'] ?? '');

        $problems = [];
        $seen = [];
        foreach ($extras as $n) {
            $key = mb_strtolower($n);
            if (mb_strlen($n) > 60) {
                $problems[] = '“'.mb_substr($n, 0, 20).'…” is too long for a name.';
            } elseif (in_array($key, $leagueKeys, true)) {
                $problems[] = "{$n} is in the league: tick them in the list instead.";
            } elseif (isset($seen[$key])) {
                $problems[] = "{$n} is in the list twice.";
            }
            $seen[$key] = true;
        }

        // Last year's champions (optional). Whoever of them is playing is marked; if both play they're a team.
        $champions = $this->names(implode(',', $data['champions'] ?? []));
        if (count(array_unique(array_map('mb_strtolower', $champions))) < count($champions)) {
            $problems[] = 'The two champions need to be two different people.';
        }

        $total = $league->count() + count($extras);
        if ($total < 4 || $total % 2 !== 0) {
            $problems[] = $total < 4
                ? "That’s {$total} ".($total === 1 ? 'player' : 'players').'. A doubles tournament needs at least four.'
                : "That’s {$total} players. Doubles needs an even number, so add or remove one.";
        }
        if ($problems) {
            return back()->withInput()->withErrors(['players' => $problems]);
        }

        $champions = array_map('mb_strtolower', $champions);
        $entrants = [];
        foreach ($league as $p) {
            $entrants[] = ['player_id' => $p->id, 'name' => $p->name, 'defending' => in_array(mb_strtolower($p->name), $champions, true)];
        }
        foreach ($extras as $n) {
            $entrants[] = ['player_id' => null, 'name' => $n, 'defending' => in_array(mb_strtolower($n), $champions, true)];
        }

        $this->tournaments->start(trim($data['name']), $entrants);

        return redirect()->route('admin.doubles.index')->with('status', 'The tournament has started. The teams and the knockout have been drawn.');
    }

    public function redraw(): RedirectResponse
    {
        $tournament = $this->running();
        try {
            $this->tournaments->redraw($tournament);
        } catch (TournamentException $e) {
            return back()->withErrors(['tournament' => $e->getMessage()]);
        }

        return redirect()->route('admin.doubles.index')->with('status', 'Drawn again.');
    }

    /** Finish: the home page goes back to the league. */
    public function end(): RedirectResponse
    {
        $this->tournaments->end($this->running());

        return redirect()->route('admin.doubles.index')->with('status', 'The tournament is finished and the home page is back to the league.');
    }

    public function destroy(): RedirectResponse
    {
        $this->tournaments->delete($this->running());

        return redirect()->route('admin.doubles.index')->with('status', 'The tournament was deleted.');
    }

    public function match(Request $request, TournamentMatch $match): View|RedirectResponse
    {
        $tournament = $this->running($match);
        $back = Dates::safePath($request->query('back'), route('admin.doubles.index', [], false));

        if (! $match->isReady()) {
            return redirect($back)->withErrors(['match' => 'That match can’t be played yet: it’s waiting for the teams.']);
        }

        $bracket = new Bracket($tournament);

        return view('admin.doubles.match', [
            'match' => $match,
            'bracket' => $bracket,
            'teams' => [$bracket->team($match->team_a_id), $bracket->team($match->team_b_id)],
            'roundName' => $bracket->roundName($match->round),
            'back' => $back,
        ]);
    }

    public function score(Request $request, TournamentMatch $match): RedirectResponse
    {
        $tournament = $this->running($match);
        $back = Dates::safePath($request->input('back'), route('admin.doubles.index', [], false));
        $min = (int) config('league.min_checkout');
        $max = (int) config('league.max_checkout');

        $data = $request->validate([
            'winner_team_id' => ['required', Rule::in(array_filter([$match->team_a_id, $match->team_b_id]))],
            'checkout' => ['nullable', 'integer', "between:{$min},{$max}"],
        ], [
            'winner_team_id.required' => 'Choose which team won.',
            'winner_team_id.in' => 'Choose one of the two teams.',
            'checkout.between' => "The checkout must be a whole number from {$min} to {$max}, or left empty.",
            'checkout.integer' => "The checkout must be a whole number from {$min} to {$max}, or left empty.",
        ]);

        try {
            $this->tournaments->setWinner($match, (int) $data['winner_team_id'], isset($data['checkout']) ? (int) $data['checkout'] : null);
        } catch (TournamentException $e) {
            return back()->withInput()->withErrors(['winner_team_id' => $e->getMessage()]);
        }

        $team = (new Bracket($tournament))->team((int) $data['winner_team_id']);

        return redirect($back)->with('status', 'Result saved: '.$team->label().' won.');
    }

    public function clear(Request $request, TournamentMatch $match): RedirectResponse
    {
        $this->running($match);
        $back = Dates::safePath($request->input('back'), route('admin.doubles.index', [], false));

        try {
            $this->tournaments->clearWinner($match);
        } catch (TournamentException $e) {
            return back()->withErrors(['winner_team_id' => $e->getMessage()]);
        }

        return redirect($back)->with('status', 'Result cleared.');
    }

    /** The tournament in progress, and (if given) a check that the match belongs to it */
    private function running(?TournamentMatch $match = null): Tournament
    {
        $tournament = Tournament::running();
        abort_unless($tournament && (! $match || $match->tournament_id === $tournament->id), 404);

        return $tournament;
    }

    /** "Sam Jones, Alex" → names, tidied, with blanks dropped @return list<string> */
    private function names(string $text): array
    {
        $names = array_map(fn ($n) => trim(preg_replace('/\s+/', ' ', $n)), preg_split('/[,\n]+/', $text));

        return array_values(array_filter($names, fn ($n) => $n !== ''));
    }
}
