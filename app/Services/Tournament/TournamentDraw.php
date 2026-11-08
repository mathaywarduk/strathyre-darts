<?php

namespace App\Services\Tournament;

use InvalidArgumentException;
use Random\Engine\Mt19937;
use Random\Randomizer;

/**
 * Pairs the players into teams at random, then lays the teams out in a
 * standard knockout bracket at random.
 *
 * Last year's champions, if both are playing, are kept together as one team.
 * When the number of teams isn't a power of two, some first-round matches
 * have only one team: they get a bye straight into round 2.
 */
final class TournamentDraw
{
    /**
     * @param  list<array{id: int, defending: bool}>  $players  anyone playing, by an id of your choosing
     * @return array{
     *     teams: list<array{a: int, b: int, defending: bool}>,
     *     matches: list<array{round: int, position: int, a: ?int, b: ?int, winner: ?int, bye: bool}>,
     *     rounds: int
     * } team numbers in matches are positions in teams
     */
    public function draw(array $players, int $seed): array
    {
        $n = count($players);
        if ($n < 4) {
            throw new InvalidArgumentException('A doubles tournament needs at least four players.');
        }
        if ($n % 2 !== 0) {
            throw new InvalidArgumentException("Doubles needs an even number of players, and there are {$n}.");
        }

        $rng = new Randomizer(new Mt19937($seed));

        $champions = array_values(array_filter($players, fn ($p) => $p['defending']));
        $teams = [];
        if (count($champions) === 2) {
            $teams[] = ['a' => $champions[0]['id'], 'b' => $champions[1]['id'], 'defending' => true];
            $rest = array_values(array_filter($players, fn ($p) => ! $p['defending']));
        } else {
            $rest = $players; // one champion alone is just another player
        }

        $rest = $rng->shuffleArray($rest);
        for ($i = 0; $i < count($rest); $i += 2) {
            $teams[] = ['a' => $rest[$i]['id'], 'b' => $rest[$i + 1]['id'], 'defending' => false];
        }
        $teams = $rng->shuffleArray($teams);

        $count = count($teams);
        $size = 1;
        while ($size < $count) {
            $size *= 2;
        }
        $rounds = (int) log($size, 2);
        $firstRound = intdiv($size, 2);
        $byes = $size - $count;

        // Which first-round matches have a bye, chosen at random
        $byePositions = array_slice($rng->shuffleArray(range(1, $firstRound)), 0, $byes);

        $matches = [];
        $next = 0;
        for ($p = 1; $p <= $firstRound; $p++) {
            if (in_array($p, $byePositions, true)) {
                $matches[] = ['round' => 1, 'position' => $p, 'a' => $next, 'b' => null, 'winner' => $next, 'bye' => true];
                $next++;
            } else {
                $matches[] = ['round' => 1, 'position' => $p, 'a' => $next, 'b' => $next + 1, 'winner' => null, 'bye' => false];
                $next += 2;
            }
        }
        for ($r = 2; $r <= $rounds; $r++) {
            for ($p = 1; $p <= intdiv($size, 2 ** $r); $p++) {
                $matches[] = ['round' => $r, 'position' => $p, 'a' => null, 'b' => null, 'winner' => null, 'bye' => false];
            }
        }

        // Teams with a bye go straight into round 2
        foreach ($matches as $m) {
            if ($m['bye']) {
                $this->place($matches, $m['round'], $m['position'], $m['winner']);
            }
        }

        return ['teams' => $teams, 'matches' => $matches, 'rounds' => $rounds];
    }

    /** Put a winning team into its slot in the next round */
    private function place(array &$matches, int $round, int $position, int $team): void
    {
        foreach ($matches as &$m) {
            if ($m['round'] === $round + 1 && $m['position'] === intdiv($position + 1, 2)) {
                $m[$position % 2 === 1 ? 'a' : 'b'] = $team;

                return;
            }
        }
    }
}
