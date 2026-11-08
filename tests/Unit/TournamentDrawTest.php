<?php

namespace Tests\Unit;

use App\Services\Tournament\TournamentDraw;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

class TournamentDrawTest extends TestCase
{
    /** @return list<array{id: int, defending: bool}> */
    private function players(int $n, array $defending = []): array
    {
        return array_map(fn ($i) => ['id' => $i, 'defending' => in_array($i, $defending, true)], range(1, $n));
    }

    public function test_everyone_is_in_exactly_one_team_of_two(): void
    {
        $plan = (new TournamentDraw)->draw($this->players(12), 5);

        $this->assertCount(6, $plan['teams']);
        $members = array_merge(...array_map(fn ($t) => [$t['a'], $t['b']], $plan['teams']));
        sort($members);
        $this->assertSame(range(1, 12), $members);
        foreach ($plan['teams'] as $t) {
            $this->assertNotSame($t['a'], $t['b']);
        }
    }

    public function test_the_same_seed_gives_the_same_draw_and_another_seed_a_different_one(): void
    {
        $d = new TournamentDraw;
        $this->assertSame($d->draw($this->players(12), 5), $d->draw($this->players(12), 5));
        $this->assertNotSame($d->draw($this->players(12), 5)['teams'], $d->draw($this->players(12), 6)['teams']);
    }

    public function test_the_champions_are_kept_together_when_both_are_playing(): void
    {
        foreach (range(1, 20) as $seed) {
            $plan = (new TournamentDraw)->draw($this->players(12, [3, 9]), $seed);

            $champs = array_values(array_filter($plan['teams'], fn ($t) => $t['defending']));
            $this->assertCount(1, $champs);
            $this->assertEqualsCanonicalizing([3, 9], [$champs[0]['a'], $champs[0]['b']]);
        }
    }

    public function test_one_champion_on_their_own_is_just_another_player(): void
    {
        $paired = [];
        foreach (range(1, 30) as $seed) {
            $plan = (new TournamentDraw)->draw($this->players(8, [3]), $seed);
            $this->assertCount(0, array_filter($plan['teams'], fn ($t) => $t['defending']));
            $team = array_values(array_filter($plan['teams'], fn ($t) => in_array(3, [$t['a'], $t['b']], true)))[0];
            $paired[] = $team['a'] === 3 ? $team['b'] : $team['a'];
        }
        $this->assertGreaterThan(3, count(array_unique($paired)), 'their partner changes from draw to draw');
    }

    public function test_the_bracket_has_the_right_number_of_matches_and_byes(): void
    {
        // players => [teams, rounds, matches, byes]
        foreach ([4 => [2, 1, 1, 0], 6 => [3, 2, 3, 1], 8 => [4, 2, 3, 0], 10 => [5, 3, 7, 3], 12 => [6, 3, 7, 2], 16 => [8, 3, 7, 0], 18 => [9, 4, 15, 7]] as $n => [$teams, $rounds, $matches, $byes]) {
            $plan = (new TournamentDraw)->draw($this->players($n), 11);

            $this->assertCount($teams, $plan['teams'], "{$n} players: teams");
            $this->assertSame($rounds, $plan['rounds'], "{$n} players: rounds");
            $this->assertCount($matches, $plan['matches'], "{$n} players: matches");
            $this->assertCount($byes, array_filter($plan['matches'], fn ($m) => $m['bye']), "{$n} players: byes");
        }
    }

    public function test_every_team_plays_in_round_one_or_gets_a_bye_into_round_two(): void
    {
        foreach ([6, 10, 12, 18] as $n) {
            $plan = (new TournamentDraw)->draw($this->players($n), 3);
            $seen = [];
            foreach ($plan['matches'] as $m) {
                if ($m['round'] === 1) {
                    foreach ([$m['a'], $m['b']] as $t) {
                        if ($t !== null) {
                            $seen[] = $t;
                        }
                    }
                }
            }
            sort($seen);
            $this->assertSame(range(0, count($plan['teams']) - 1), $seen, "{$n} players: each team appears once in round 1");
        }
    }

    public function test_a_bye_sends_the_team_straight_into_the_next_round(): void
    {
        $plan = (new TournamentDraw)->draw($this->players(12), 4); // 6 teams, 2 byes
        $byMatch = [];
        foreach ($plan['matches'] as $m) {
            $byMatch[$m['round'].'-'.$m['position']] = $m;
        }

        foreach ($plan['matches'] as $m) {
            if (! $m['bye']) {
                continue;
            }
            $this->assertNull($m['b']);
            $this->assertSame($m['a'], $m['winner']);
            $parent = $byMatch[($m['round'] + 1).'-'.intdiv($m['position'] + 1, 2)];
            $this->assertSame($m['winner'], $parent[$m['position'] % 2 === 1 ? 'a' : 'b'], 'they are already in the next match');
        }
        // And nobody has a bye and a second free pass: a bye match has no winner other than its team
        $this->assertCount(2, array_filter($plan['matches'], fn ($m) => $m['bye']));
    }

    public function test_later_rounds_start_empty(): void
    {
        $plan = (new TournamentDraw)->draw($this->players(16), 2); // 8 teams, no byes
        foreach ($plan['matches'] as $m) {
            if ($m['round'] > 1) {
                $this->assertNull($m['a']);
                $this->assertNull($m['b']);
                $this->assertNull($m['winner']);
            }
        }
    }

    public function test_it_needs_an_even_number_of_at_least_four(): void
    {
        $d = new TournamentDraw;
        foreach ([0, 2, 3, 5, 9] as $n) {
            try {
                $d->draw($this->players($n), 1);
                $this->fail("{$n} players should be refused");
            } catch (InvalidArgumentException) {
                $this->addToAssertionCount(1);
            }
        }
    }
}
