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
     * Sorted by points, then score difference, then name.
     *
     * @return Collection<int, array{player: Player, pos: int, played: int, won: int, lost: int, diff: int, pts: int}>
     */
    public function table(): Collection
    {
        if ($this->table) {
            return $this->table;
        }

        $rows = $this->players->map(fn (Player $p) => [
            'player' => $p, 'pos' => 0, 'played' => 0, 'won' => 0, 'lost' => 0, 'diff' => 0, 'pts' => 0,
        ])->all();

        foreach ($this->fixtures as $fixture) {
            $result = $fixture->result;
            if (! $result) {
                continue;
            }
            $winner = $result->winner_id;
            $loser = $fixture->opponentOf($winner);
            $rows[$winner]['played']++;
            $rows[$winner]['won']++;
            $rows[$winner]['diff'] += $result->remaining;
            $rows[$loser]['played']++;
            $rows[$loser]['lost']++;
            $rows[$loser]['diff'] -= $result->remaining;
        }

        $win = $this->settings->win_points ?? 0;
        $loss = $this->settings->loss_points ?? 0;
        foreach ($rows as &$row) {
            $row['pts'] = $row['won'] * $win + $row['lost'] * $loss;
        }
        unset($row);

        $sorted = collect($rows)->sort(fn ($a, $b) => [$b['pts'], $b['diff'], mb_strtolower($a['player']->name)]
            <=> [$a['pts'], $a['diff'], mb_strtolower($b['player']->name)])->values();

        return $this->table = $sorted->map(function ($row, $i) {
            $row['pos'] = $i + 1;

            return $row;
        })->keyBy(fn ($row) => $row['player']->id)->sortBy('pos');
    }

    /** @return array{pos: int, played: int, won: int, lost: int, diff: int, pts: int, player: Player} */
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
