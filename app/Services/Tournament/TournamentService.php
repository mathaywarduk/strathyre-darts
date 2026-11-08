<?php

namespace App\Services\Tournament;

use App\Models\Tournament;
use App\Models\TournamentEntrant;
use App\Models\TournamentMatch;
use App\Models\TournamentTeam;
use Illuminate\Support\Facades\DB;

final class TournamentService
{
    public function __construct(private TournamentDraw $draw) {}

    /**
     * Start a tournament: save who is playing, make the teams and the bracket.
     *
     * @param  list<array{player_id: ?int, name: string, defending: bool}>  $players
     */
    public function start(string $name, array $players, ?int $seed = null): Tournament
    {
        return DB::transaction(function () use ($name, $players, $seed) {
            $tournament = Tournament::create(['name' => $name, 'status' => 'running', 'started_at' => now()]);

            foreach ($players as $p) {
                $tournament->entrants()->create(['player_id' => $p['player_id'], 'name' => $p['name'], 'defending' => $p['defending']]);
            }
            $this->lay($tournament, $seed);

            return $tournament;
        });
    }

    /** Draw the teams and bracket again with the same players, while no match has been played */
    public function redraw(Tournament $tournament, ?int $seed = null): void
    {
        if ($tournament->matches()->where('is_bye', false)->whereNotNull('winner_team_id')->exists()) {
            throw new TournamentException('A match has already been played, so the draw can’t be redone.');
        }

        DB::transaction(function () use ($tournament, $seed) {
            DB::table('tournament_matches')->where('tournament_id', $tournament->id)->delete();
            DB::table('tournament_teams')->where('tournament_id', $tournament->id)->delete();
            $this->lay($tournament, $seed);
        });
    }

    /** Record who won a match, and send them into the next round. The checkout is optional. */
    public function setWinner(TournamentMatch $match, int $teamId, ?int $checkout): void
    {
        if (! $match->isReady()) {
            throw new TournamentException('That match can’t be played yet: it’s waiting for the teams.');
        }
        if (! in_array($teamId, [$match->team_a_id, $match->team_b_id], true)) {
            throw new TournamentException('The winner has to be one of the two teams in the match.');
        }

        $parent = $this->parentOf($match);
        if ($match->winner_team_id !== null && $match->winner_team_id !== $teamId && $parent?->isPlayed()) {
            throw new TournamentException('The next round has already been played. Clear that result first.');
        }

        DB::transaction(function () use ($match, $teamId, $checkout, $parent) {
            $match->update(['winner_team_id' => $teamId, 'checkout' => $checkout]);
            if ($parent) {
                $parent->update([$this->slot($match) => $teamId]);
            }
        });
    }

    /** Take a result back, so the match can be played again */
    public function clearWinner(TournamentMatch $match): void
    {
        if ($match->is_bye) {
            throw new TournamentException('A bye can’t be cleared.');
        }
        $parent = $this->parentOf($match);
        if ($parent?->isPlayed()) {
            throw new TournamentException('The next round has already been played. Clear that result first.');
        }

        DB::transaction(function () use ($match, $parent) {
            $match->update(['winner_team_id' => null, 'checkout' => null]);
            if ($parent) {
                $parent->update([$this->slot($match) => null]);
            }
        });
    }

    /** Finish: the home page goes back to the league, and the result is kept */
    public function end(Tournament $tournament): void
    {
        $tournament->update(['status' => 'ended', 'ended_at' => now()]);
    }

    /** Remove a tournament and everything in it */
    public function delete(Tournament $tournament): void
    {
        DB::transaction(function () use ($tournament) {
            DB::table('tournament_matches')->where('tournament_id', $tournament->id)->delete();
            DB::table('tournament_teams')->where('tournament_id', $tournament->id)->delete();
            DB::table('tournament_entrants')->where('tournament_id', $tournament->id)->delete();
            $tournament->delete();
        });
    }

    public function parentOf(TournamentMatch $match): ?TournamentMatch
    {
        return TournamentMatch::query()
            ->where('tournament_id', $match->tournament_id)
            ->where('round', $match->round + 1)
            ->where('position', intdiv($match->position + 1, 2))
            ->first();
    }

    private function slot(TournamentMatch $match): string
    {
        return $match->position % 2 === 1 ? 'team_a_id' : 'team_b_id';
    }

    private function lay(Tournament $tournament, ?int $seed): void
    {
        $entrants = $tournament->entrants()->get();
        $plan = $this->draw->draw(
            $entrants->map(fn (TournamentEntrant $e) => ['id' => $e->id, 'defending' => $e->defending])->all(),
            $seed ?? random_int(1, 2_000_000_000),
        );

        $teamIds = [];
        foreach ($plan['teams'] as $i => $t) {
            $teamIds[$i] = TournamentTeam::create([
                'tournament_id' => $tournament->id, 'entrant_a_id' => $t['a'], 'entrant_b_id' => $t['b'], 'defending' => $t['defending'],
            ])->id;
        }
        foreach ($plan['matches'] as $m) {
            TournamentMatch::create([
                'tournament_id' => $tournament->id, 'round' => $m['round'], 'position' => $m['position'],
                'team_a_id' => $m['a'] === null ? null : $teamIds[$m['a']],
                'team_b_id' => $m['b'] === null ? null : $teamIds[$m['b']],
                'winner_team_id' => $m['winner'] === null ? null : $teamIds[$m['winner']],
                'is_bye' => $m['bye'],
            ]);
        }
    }
}
