<?php

namespace Tests\Unit;

use App\Services\FixtureGenerator;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

class FixtureGeneratorTest extends TestCase
{
    private function players(int $n): array
    {
        return array_map(fn ($i) => ['id' => $i + 1, 'strength' => ($i % 5) + 1], range(0, $n - 1));
    }

    private function assertEveryPairOnce(array $weeks, int $n): void
    {
        $pairs = [];
        foreach ($weeks as $week) {
            foreach ($week as [$a, $b]) {
                $key = min($a, $b).'-'.max($a, $b);
                $this->assertArrayNotHasKey($key, $pairs, "Pair {$key} appears twice");
                $pairs[$key] = true;
            }
        }
        $this->assertCount($n * ($n - 1) / 2, $pairs);
    }

    private function maxPerWeek(array $week): int
    {
        return max(array_count_values(array_merge(...array_map(fn ($m) => [$m[0], $m[1]], $week))));
    }

    public function test_twenty_players_make_nineteen_weeks_of_ten_matches(): void
    {
        $plan = (new FixtureGenerator)->generate($this->players(20), null, 1);

        $this->assertCount(19, $plan['weeks']);
        foreach ($plan['weeks'] as $week) {
            $this->assertCount(10, $week);
            $this->assertSame(1, $this->maxPerWeek($week), 'everyone plays once a week');
        }
        $this->assertEveryPairOnce($plan['weeks'], 20);
    }

    public function test_the_biggest_matches_come_last(): void
    {
        $plan = (new FixtureGenerator)->generate($this->players(20), null, 3);

        $sorted = $plan['heat'];
        sort($sorted);
        $this->assertSame($sorted, $plan['heat'], 'weeks warm up from first to last');
        $this->assertGreaterThan(array_sum(array_slice($plan['heat'], 0, 3)), array_sum(array_slice($plan['heat'], -3)));

        // The top-v-top matches (both strength 5) are all in the last few weeks
        $weeksWithBig = [];
        foreach ($plan['weeks'] as $i => $week) {
            foreach ($week as [$a, $b]) {
                if ($a % 5 === 0 && $b % 5 === 0) {
                    $weeksWithBig[] = $i + 1;
                }
            }
        }
        $this->assertNotEmpty($weeksWithBig);
        $this->assertGreaterThanOrEqual(10, min($weeksWithBig));
    }

    public function test_the_same_seed_gives_the_same_plan_and_a_new_seed_a_different_one(): void
    {
        $g = new FixtureGenerator;
        $this->assertSame($g->generate($this->players(20), null, 5), $g->generate($this->players(20), null, 5));
        $this->assertNotSame($g->generate($this->players(20), null, 5)['weeks'], $g->generate($this->players(20), null, 6)['weeks']);
    }

    public function test_an_odd_number_of_players_gets_byes(): void
    {
        $plan = (new FixtureGenerator)->generate($this->players(19), null, 1);

        $this->assertCount(19, $plan['weeks']);
        foreach ($plan['weeks'] as $week) {
            $this->assertCount(9, $week);
            $this->assertSame(1, $this->maxPerWeek($week));
        }
        $this->assertEveryPairOnce($plan['weeks'], 19);
    }

    public function test_fewer_weeks_means_some_players_play_twice_on_a_night(): void
    {
        $plan = (new FixtureGenerator)->generate($this->players(20), 15, 1);

        $this->assertCount(15, $plan['weeks']);
        $this->assertEveryPairOnce($plan['weeks'], 20);
        $max = max(array_map(fn ($w) => $this->maxPerWeek($w), $plan['weeks']));
        $this->assertSame(2, $max, 'nobody plays more than twice in a night, and some play twice');
    }

    public function test_the_fewest_possible_weeks_still_works(): void
    {
        $plan = (new FixtureGenerator)->generate($this->players(20), 10, 1);

        $this->assertCount(10, $plan['weeks']);
        $this->assertEveryPairOnce($plan['weeks'], 20);
        $this->assertLessThanOrEqual(2, max(array_map(fn ($w) => $this->maxPerWeek($w), $plan['weeks'])));
    }

    public function test_too_few_weeks_is_refused(): void
    {
        $this->expectException(InvalidArgumentException::class);
        (new FixtureGenerator)->generate($this->players(20), 5, 1);
    }
}
