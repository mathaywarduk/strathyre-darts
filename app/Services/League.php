<?php

namespace App\Services;

use App\Models\Fixture;
use App\Models\LeagueNight;
use App\Models\Player;
use App\Models\Season;
use App\Models\Setting;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;

/**
 * Everything about one season, worked out from its fixtures and results:
 * the table, leaders, the current week, catch-ups. Nothing is stored, so
 * editing a result or a setting is always reflected straight away.
 */
final class League
{
    /** @var Collection<int, LeagueNight> ordered by week */
    public readonly Collection $nights;

    /** @var Collection<int, Fixture> each with its result and night loaded */
    public readonly Collection $fixtures;

    /** @var Collection<int, Player> keyed by id, ordered by name; only players with fixtures */
    public readonly Collection $players;

    private ?Collection $table = null;

    public function __construct(public readonly Season $season, public readonly Setting $settings)
    {
        $this->nights = $season->nights()->get();
        $nightsById = $this->nights->keyBy('id');

        $this->fixtures = $season->fixtures()->with('result')->orderBy('id')->get();
        foreach ($this->fixtures as $fixture) {
            $fixture->setRelation('night', $nightsById[$fixture->league_night_id]);
        }

        $ids = $this->fixtures->flatMap(fn (Fixture $f) => [$f->player1_id, $f->player2_id])->unique()->all();
        $this->players = Player::query()->whereIn('id', $ids)->get()
            ->sortBy(fn (Player $p) => mb_strtolower($p->name))->keyBy('id');
    }

    public function hasFixtures(): bool
    {
        return $this->fixtures->isNotEmpty();
    }

    public function isLive(): bool
    {
        return $this->settings->isComplete() && $this->hasFixtures();
    }

    public function name(int $playerId): string
    {
        return $this->players[$playerId]->name;
    }

    /* ---------------------------------------------------------------- */
    /* Table */

    /**
     * Sorted by points. Players level on points are split by head-to-head: who beat
     * whom, then (for three or more) by wins against each other. Anyone still level
     * goes alphabetically while the season runs. Once every match is played they
     * share a position instead, because a play-off settles it.
     *
     * @return Collection<int, array{player: Player, pos: int, tied: bool, played: int, won: int, lost: int, pts: int}>
     */
    public function table(): Collection
    {
        if ($this->table) {
            return $this->table;
        }

        $stats = $this->players->map(fn (Player $p) => [
            'player' => $p, 'pos' => 0, 'tied' => false, 'played' => 0, 'won' => 0, 'lost' => 0, 'pts' => 0,
        ])->all();

        foreach ($this->fixtures as $fixture) {
            if (! $fixture->result) {
                continue;
            }
            $winner = $fixture->result->winner_id;
            $loser = $fixture->opponentOf($winner);
            $stats[$winner]['played']++;
            $stats[$winner]['won']++;
            $stats[$loser]['played']++;
            $stats[$loser]['lost']++;
        }

        $win = $this->settings->win_points ?? 0;
        $loss = $this->settings->loss_points ?? 0;
        foreach ($stats as &$row) {
            $row['pts'] = $row['won'] * $win + $row['lost'] * $loss;
        }
        unset($row);

        $complete = $this->isComplete();
        $rows = [];
        $i = 0;
        foreach (collect($stats)->groupBy('pts')->sortKeysDesc() as $level) {
            $ids = $level->map(fn ($r) => $r['player']->id)->values()->all();

            foreach ($this->breakTie($ids) as $block) {
                usort($block, fn ($a, $b) => mb_strtolower($this->players[$a]->name) <=> mb_strtolower($this->players[$b]->name));
                $shared = $complete && count($block) > 1;
                $start = $i;
                foreach ($block as $id) {
                    $rows[$id] = [...$stats[$id], 'pos' => ($shared ? $start : $i) + 1, 'tied' => $shared];
                    $i++;
                }
            }
        }

        return $this->table = collect($rows);
    }

    /**
     * Splits players level on points using the matches they played against each other.
     *
     * @param  list<int>  $ids
     * @return list<list<int>> blocks in order; a block of more than one is a tie nothing could split
     */
    private function breakTie(array $ids): array
    {
        if (count($ids) === 1) {
            return [$ids];
        }

        $wins = array_fill_keys($ids, 0);
        foreach ($this->fixtures as $fixture) {
            if (! $fixture->result) {
                continue;
            }
            $winner = $fixture->result->winner_id;
            if (isset($wins[$winner]) && isset($wins[$fixture->opponentOf($winner)])) {
                $wins[$winner]++;
            }
        }

        $buckets = [];
        foreach ($wins as $id => $n) {
            $buckets[$n][] = $id;
        }
        if (count($buckets) === 1) {
            return [$ids]; // none played, or they all beat each other in a circle
        }

        krsort($buckets);
        $blocks = [];
        foreach ($buckets as $bucket) {
            foreach ($this->breakTie($bucket) as $block) {
                $blocks[] = $block;
            }
        }

        return $blocks;
    }

    /** Every fixture has a result: the season is over, bar any play-offs */
    public function isComplete(): bool
    {
        return $this->hasFixtures() && $this->fixtures->every(fn (Fixture $f) => (bool) $f->result);
    }

    /**
     * The best winning checkout in the league, with everyone who hit it (earliest week first).
     *
     * @return array{checkout: int, fixtures: Collection<int, Fixture>}|null
     */
    public function bestCheckout(): ?array
    {
        $wins = $this->fixtures->filter(fn (Fixture $f) => $f->result);
        if ($wins->isEmpty()) {
            return null;
        }
        $best = $wins->max(fn (Fixture $f) => $f->result->checkout);

        return [
            'checkout' => $best,
            'fixtures' => $wins->filter(fn (Fixture $f) => $f->result->checkout === $best)
                ->sortBy(fn (Fixture $f) => [$f->night->week, $f->id])->values(),
        ];
    }

    /** @return list<int> everyone who has hit the league's best checkout */
    public function bestCheckoutPlayerIds(): array
    {
        $best = $this->bestCheckout();

        return $best ? $best['fixtures']->map(fn (Fixture $f) => $f->result->winner_id)->unique()->values()->all() : [];
    }

    /** A player's best winning checkout, or null if they haven't won yet */
    public function highestCheckout(int $playerId): ?int
    {
        return $this->fixtures
            ->filter(fn (Fixture $f) => $f->result && $f->result->winner_id === $playerId)
            ->max(fn (Fixture $f) => $f->result->checkout);
    }

    /** @return array{pos: int, tied: bool, played: int, won: int, lost: int, pts: int, player: Player} */
    public function row(int $playerId): array
    {
        return $this->table()[$playerId];
    }

    /**
     * The highest-placed woman and man, once they have played at least once
     * (otherwise week 1 would crown whoever comes first alphabetically).
     *
     * @return array{W: ?int, M: ?int}
     */
    public function leaders(): array
    {
        $leaders = ['W' => null, 'M' => null];
        foreach ($this->table() as $row) {
            $g = $row['player']->gender;
            if ($row['played'] > 0 && $leaders[$g] === null) {
                $leaders[$g] = $row['player']->id;
            }
        }

        return $leaders;
    }

    /* ---------------------------------------------------------------- */
    /* Weeks */

    /** The first week whose results haven't been submitted */
    public function currentNight(): ?LeagueNight
    {
        return $this->nights->first(fn (LeagueNight $n) => ! $n->isSubmitted());
    }

    /** The most recent week whose results have been submitted */
    public function lastNight(): ?LeagueNight
    {
        return $this->nights->filter(fn (LeagueNight $n) => $n->isSubmitted())->last();
    }

    public function weeksPlayed(): int
    {
        return $this->nights->filter(fn (LeagueNight $n) => $n->isSubmitted())->count();
    }

    public function totalWeeks(): int
    {
        return $this->nights->count();
    }

    /**
     * Friday night has come and gone (or it's after the cut-off hour tonight)
     * but its results haven't been submitted yet.
     */
    public function resultsPending(CarbonInterface $now): bool
    {
        $night = $this->currentNight();
        if (! $night) {
            return false;
        }
        $day = $now->copy()->startOfDay();

        return $night->date->lt($day)
            || ($night->date->eq($day) && $now->hour >= config('league.pending_hour'));
    }

    /** @return Collection<int, Fixture> */
    public function fixturesForNight(LeagueNight $night): Collection
    {
        return $this->fixtures->where('league_night_id', $night->id)->values();
    }

    /** Players with no match that week (odd numbers) @return Collection<int, Player> */
    public function byes(LeagueNight $night): Collection
    {
        $playing = $this->fixturesForNight($night)->flatMap(fn (Fixture $f) => [$f->player1_id, $f->player2_id])->all();

        return $this->players->reject(fn (Player $p) => in_array($p->id, $playing, true))->values();
    }

    public function isCatchUp(Fixture $fixture): bool
    {
        return ! $fixture->result && $fixture->night->isSubmitted();
    }

    public function isUpcoming(Fixture $fixture): bool
    {
        return ! $fixture->result && ! $fixture->night->isSubmitted();
    }

    /** Postponed or missed matches still to be played @return Collection<int, Fixture> */
    public function catchUps(): Collection
    {
        return $this->fixtures->filter(fn (Fixture $f) => $this->isCatchUp($f))
            ->sortBy(fn (Fixture $f) => [$f->night->week, $f->id])->values();
    }

    /** Results entered with this week's submission, in fixture-week order @return Collection<int, Fixture> */
    public function resultsSubmittedWith(LeagueNight $night): Collection
    {
        return $this->fixtures->filter(fn (Fixture $f) => $f->result && $f->result->submitted_night_id === $night->id)
            ->sortBy(fn (Fixture $f) => [$f->night->week, $f->id])->values();
    }

    /** @return Collection<int, Fixture> every fixture the player is in, by week */
    public function fixturesFor(int $playerId): Collection
    {
        return $this->fixtures->filter(fn (Fixture $f) => $f->involves($playerId))
            ->sortBy(fn (Fixture $f) => [$f->night->week, $f->id])->values();
    }
}
