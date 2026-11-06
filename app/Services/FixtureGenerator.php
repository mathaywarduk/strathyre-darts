<?php

namespace App\Services;

use InvalidArgumentException;
use Random\Engine\Mt19937;
use Random\Randomizer;

/**
 * Builds a single round robin (every pair meets once), then arranges it so
 * the biggest matches (strong v strong) land in the last weeks.
 *
 * Every week of a plain round robin has every player once, so the weeks only
 * differ in WHO is paired with WHOM. A match's "heat" is (strength × strength)²,
 * which makes 5 v 5 far hotter than 5 v 1. We try many player placements and
 * keep the one whose weeks warm up most steadily, with the hottest last.
 *
 * With an odd number of players one person has a bye each week. If fewer
 * weeks are asked for than a full round robin needs, the extra matches are
 * spread into other weeks, so some players play twice on a night (never more).
 */
final class FixtureGenerator
{
    private const ATTEMPTS = 6;

    private const STEPS = 1200;

    /**
     * @param  list<array{id: int, strength: int}>  $players  strength 1-5, bigger = stronger
     * @return array{weeks: list<list<array{0: int, 1: int}>>, heat: list<int>}
     */
    public function generate(array $players, ?int $maxWeeks, int $seed): array
    {
        $n = count($players);
        if ($n < 2) {
            throw new InvalidArgumentException('Upload at least two players first.');
        }

        $rounds = $n % 2 ? $n : $n - 1;
        $weeks = $maxWeeks === null ? $rounds : min($maxWeeks, $rounds);
        $fewest = (int) ceil($rounds / 2);
        if ($weeks < $fewest) {
            throw new InvalidArgumentException("With {$n} players the season needs at least {$fewest} league nights (a player can play twice in a night, but no more).");
        }

        $strength = [];
        foreach ($players as $p) {
            $strength[$p['id']] = $p['strength'];
        }
        $ids = array_keys($strength);
        $rng = new Randomizer(new Mt19937($seed));

        $bestOrder = null;
        $bestScore = -1;
        for ($attempt = 0; $attempt < self::ATTEMPTS; $attempt++) {
            $order = $rng->shuffleArray($ids);
            $score = $this->score($order, $strength, $weeks);
            for ($step = 0; $step < self::STEPS; $step++) {
                $i = $rng->getInt(0, $n - 1);
                $j = $rng->getInt(0, $n - 1);
                if ($i === $j) {
                    continue;
                }
                [$order[$i], $order[$j]] = [$order[$j], $order[$i]];
                $new = $this->score($order, $strength, $weeks);
                if ($new >= $score) {
                    $score = $new;
                } else {
                    [$order[$i], $order[$j]] = [$order[$j], $order[$i]];
                }
            }
            if ($score > $bestScore) {
                $bestScore = $score;
                $bestOrder = $order;
            }
        }

        return $this->build($bestOrder, $strength, $weeks, $rng);
    }

    /** Round robin by the circle method @return list<list<array{0: int, 1: int}>> */
    public function circle(array $order): array
    {
        $list = array_values($order);
        if (count($list) % 2) {
            $list[] = null; // the bye
        }
        $m = count($list);
        $rounds = [];
        for ($r = 0; $r < $m - 1; $r++) {
            $pairs = [];
            for ($i = 0; $i < $m / 2; $i++) {
                $a = $list[$i];
                $b = $list[$m - 1 - $i];
                if ($a !== null && $b !== null) {
                    $pairs[] = [$a, $b];
                }
            }
            $rounds[] = $pairs;
            $list = array_merge([$list[0]], [$list[$m - 1]], array_slice($list, 1, $m - 2));
        }

        return $rounds;
    }

    public function heat(array $pair, array $strength): int
    {
        return (int) (($strength[$pair[0]] * $strength[$pair[1]]) ** 2);
    }

    /** Higher = hotter weeks later. Rearrangement: sorted ascending maximises Σ week × heat. */
    private function score(array $order, array $strength, int $weeks): int
    {
        $heats = array_map(fn ($round) => array_sum(array_map(fn ($p) => $this->heat($p, $strength), $round)), $this->circle($order));
        sort($heats);
        $base = array_slice($heats, -$weeks);

        $score = 0;
        foreach ($base as $k => $h) {
            $score += $k * $h;
        }

        return $score;
    }

    private function build(array $order, array $strength, int $weeks, Randomizer $rng): array
    {
        $rounds = $this->circle($order);
        $heats = array_map(fn ($round) => array_sum(array_map(fn ($p) => $this->heat($p, $strength), $round)), $rounds);
        $byHeat = array_keys($heats);
        usort($byHeat, fn ($a, $b) => [$heats[$a], $a] <=> [$heats[$b], $b]);

        $extra = count($rounds) - $weeks;
        $surplus = array_slice($byHeat, 0, $extra);
        $baseIdx = array_slice($byHeat, $extra);

        $plan = array_map(fn ($i) => $rounds[$i], $baseIdx);

        if ($extra > 0) {
            $matches = [];
            foreach ($surplus as $i) {
                foreach ($rounds[$i] as $pair) {
                    $matches[] = $pair;
                }
            }
            usort($matches, fn ($a, $b) => $this->heat($a, $strength) <=> $this->heat($b, $strength));

            $placed = null;
            for ($try = 0; $try < 40 && $placed === null; $try++) {
                $placed = $this->spread($plan, $matches);
                if ($placed === null) {
                    $matches = $rng->shuffleArray($matches);
                }
            }
            if ($placed === null) {
                // Always possible: give each surplus round a week of its own
                $placed = $plan;
                foreach (array_values($surplus) as $k => $i) {
                    $placed[$k] = array_merge($placed[$k], $rounds[$i]);
                }
            }
            $plan = $placed;
        }

        return [
            'weeks' => $plan,
            'heat' => array_map(fn ($week) => array_sum(array_map(fn ($p) => $this->heat($p, $strength), $week)), $plan),
        ];
    }

    /**
     * Put each extra match in the quietest week where neither player already
     * has an extra match. Null if some match can't be placed.
     */
    private function spread(array $plan, array $matches): ?array
    {
        $extraBy = array_fill(0, count($plan), []);
        foreach ($matches as $pair) {
            $best = null;
            foreach ($plan as $w => $week) {
                if (isset($extraBy[$w][$pair[0]]) || isset($extraBy[$w][$pair[1]])) {
                    continue;
                }
                if ($best === null || count($week) < count($plan[$best])) {
                    $best = $w;
                }
            }
            if ($best === null) {
                return null;
            }
            $plan[$best][] = $pair;
            $extraBy[$best][$pair[0]] = $extraBy[$best][$pair[1]] = true;
        }

        return $plan;
    }
}
