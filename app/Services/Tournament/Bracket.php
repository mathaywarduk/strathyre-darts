<?php

namespace App\Services\Tournament;

use App\Models\Tournament;
use App\Models\TournamentMatch;
use App\Models\TournamentTeam;
use Illuminate\Support\Collection;

/** A tournament loaded for display: its matches by round and position, and its teams. */
final class Bracket
{
    /** @var Collection<string, TournamentMatch> keyed "round-position" */
    public readonly Collection $matches;

    /** @var Collection<int, TournamentTeam> keyed by id */
    public readonly Collection $teams;

    public readonly int $rounds;

    public function __construct(public readonly Tournament $tournament)
    {
        $this->teams = $tournament->teams()->with(['entrantA', 'entrantB'])->get()->keyBy('id');
        $this->matches = $tournament->matches()->get()->keyBy(fn (TournamentMatch $m) => $m->round.'-'.$m->position);
        $this->rounds = (int) $this->matches->max('round');
    }

    public function match(int $round, int $position): ?TournamentMatch
    {
        return $this->matches->get($round.'-'.$position);
    }

    /** The two matches that feed this one (none in round 1) @return list<TournamentMatch> */
    public function feeders(TournamentMatch $match): array
    {
        if ($match->round === 1) {
            return [];
        }

        return array_values(array_filter([
            $this->match($match->round - 1, $match->position * 2 - 1),
            $this->match($match->round - 1, $match->position * 2),
        ]));
    }

    public function final(): ?TournamentMatch
    {
        return $this->match($this->rounds, 1);
    }

    public function team(?int $id): ?TournamentTeam
    {
        return $id === null ? null : $this->teams->get($id);
    }

    public function champions(): ?TournamentTeam
    {
        return $this->team($this->final()?->winner_team_id);
    }

    /** "Final", "Semi-finals", "Quarter-finals", "Round of 16"… */
    public function roundName(int $round): string
    {
        return match ($this->rounds - $round) {
            0 => 'Final',
            1 => 'Semi-finals',
            2 => 'Quarter-finals',
            3 => 'Round of 16',
            4 => 'Round of 32',
            default => 'Round '.$round,
        };
    }

    /** @return Collection<int, Collection<int, TournamentMatch>> matches grouped by round */
    public function byRound(): Collection
    {
        return $this->matches->groupBy('round')->map(fn ($g) => $g->sortBy('position')->values())->sortKeys();
    }

    public function played(): int
    {
        return $this->matches->filter(fn (TournamentMatch $m) => ! $m->is_bye && $m->isPlayed())->count();
    }

    public function toPlay(): int
    {
        return $this->matches->filter(fn (TournamentMatch $m) => ! $m->is_bye)->count();
    }
}
