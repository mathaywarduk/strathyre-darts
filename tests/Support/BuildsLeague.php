<?php

namespace Tests\Support;

use App\Models\Fixture;
use App\Models\LeagueNight;
use App\Models\Player;
use App\Models\Season;
use App\Models\Setting;
use App\Models\User;
use App\Services\FixtureGenerator;
use App\Services\SeasonCalendar;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;

trait BuildsLeague
{
    private array $sampleNames = [
        'Alice A', 'Bob B', 'Cara C', 'Dan D', 'Eve E', 'Finn F', 'Gail G', 'Hugh H', 'Iris I', 'Jack J',
        'Kate K', 'Liam L', 'Mona M', 'Ned N', 'Orla O', 'Pete P', 'Quinn Q', 'Rae R', 'Sam S', 'Tess T',
    ];

    /** Rankings run 1-5 round the list; women and men alternate */
    protected function makePlayers(int $n = 20): Collection
    {
        return collect(range(0, $n - 1))->map(fn ($i) => Player::create([
            'name' => $this->sampleNames[$i] ?? "Player {$i}",
            'ranking' => ($i % 5) + 1,
            'gender' => $i % 2 ? 'M' : 'W',
        ]));
    }

    protected function liveLeague(int $n = 20, ?int $maxWeeks = null, string $start = '2026-11-13'): Season
    {
        Setting::current()->update(['win_points' => 2, 'loss_points' => 1, 'subs_pence' => 200]);
        $players = $this->makePlayers($n);
        $season = Season::create(['starts_on' => $start, 'status' => 'current']);

        $plan = (new FixtureGenerator)->generate($players->map(fn ($p) => ['id' => $p->id, 'strength' => $p->ranking])->all(), $maxWeeks, 7);
        $dates = SeasonCalendar::dates(CarbonImmutable::parse($start), count($plan['weeks']), []);

        foreach ($plan['weeks'] as $i => $matches) {
            $night = LeagueNight::create(['season_id' => $season->id, 'week' => $i + 1, 'date' => $dates[$i]->toDateString()]);
            foreach ($matches as [$a, $b]) {
                Fixture::create([
                    'season_id' => $season->id, 'league_night_id' => $night->id,
                    'player1_id' => $a, 'player2_id' => $b, 'pair_key' => Fixture::pairKey($a, $b),
                ]);
            }
        }

        return $season;
    }

    protected function admin(): User
    {
        return User::where('username', 'admin')->first() ?? User::factory()->create();
    }

    protected function night(Season $season, int $week): LeagueNight
    {
        return LeagueNight::where(['season_id' => $season->id, 'week' => $week])->firstOrFail();
    }

    /** The first fixture of a week as [fixture, winner name, loser name] */
    protected function firstMatch(LeagueNight $night): array
    {
        $f = Fixture::where('league_night_id', $night->id)->orderBy('id')->firstOrFail();

        return [$f, Player::find($f->player1_id)->name, Player::find($f->player2_id)->name];
    }

    protected function submitLines(LeagueNight $night, string $lines): void
    {
        $this->actingAs($this->admin())
            ->post(route('admin.results.save'), ['week' => $night->id, 'mode' => 'results', 'lines' => $lines])
            ->assertRedirect(route('admin.dashboard'));
    }
}
