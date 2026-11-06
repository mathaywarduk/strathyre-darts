<?php

namespace App\Services;

use App\Models\Fixture;
use App\Models\Player;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;

/** Reads a fixtures CSV (week, date, player 1, player 2) and checks it before anything is saved. */
final class FixtureImport
{
    /**
     * @param  Collection<int, Player>  $players
     * @return array{weeks: list<array{week: int, date: string, matches: list<array{0: int, 1: int}>}>, errors: list<string>}
     */
    public function parse(string $text, Collection $players): array
    {
        $byName = $players->keyBy(fn (Player $p) => mb_strtolower(preg_replace('/\s+/', ' ', $p->name)));
        $errors = [];
        $dates = [];   // week => Y-m-d
        $matches = []; // week => list of [id, id]
        $pairs = [];

        foreach (Csv::rows($text) as $n => ['line' => $line, 'cells' => $c]) {
            if ($n === 0 && strtolower($c[0] ?? '') === 'week') {
                continue;
            }
            if (count($c) < 4) {
                $errors[] = "Line {$line}: expected week, date, player 1, player 2.";

                continue;
            }
            [$week, $date, $a, $b] = $c;
            $problems = [];

            if (! preg_match('/^\d{1,3}$/', $week) || (int) $week < 1) {
                $problems[] = 'week must be a whole number from 1';
            }
            $parsed = $this->date($date);
            if (! $parsed) {
                $problems[] = 'date must look like 2026-11-13 or 13/11/2026';
            }
            $p1 = $byName->get(mb_strtolower(preg_replace('/\s+/', ' ', $a)));
            $p2 = $byName->get(mb_strtolower(preg_replace('/\s+/', ' ', $b)));
            if (! $p1) {
                $problems[] = "no player called “{$a}”";
            }
            if (! $p2) {
                $problems[] = "no player called “{$b}”";
            }
            if ($p1 && $p2 && $p1->id === $p2->id) {
                $problems[] = 'a player can’t play themselves';
            }
            if ($p1 && $p2 && $p1->id !== $p2->id) {
                $key = Fixture::pairKey($p1->id, $p2->id);
                if (isset($pairs[$key])) {
                    $problems[] = "{$p1->name} and {$p2->name} are already down to play on line {$pairs[$key]}";
                }
            }
            if ($parsed && ! $problems && isset($dates[(int) $week]) && $dates[(int) $week] !== $parsed->toDateString()) {
                $problems[] = "week {$week} has a different date on an earlier line";
            }
            if ($problems) {
                $errors[] = "Line {$line}: ".implode('; ', $problems).'.';

                continue;
            }

            $dates[(int) $week] = $parsed->toDateString();
            $matches[(int) $week][] = [$p1->id, $p2->id];
            $pairs[Fixture::pairKey($p1->id, $p2->id)] = $line;
        }

        if ($dates) {
            ksort($dates);
            $expected = range(1, max(array_keys($dates)));
            $missing = array_diff($expected, array_keys($dates));
            if ($missing) {
                $errors[] = 'Weeks must run from 1 with no gaps. Missing: '.implode(', ', $missing).'.';
            }
        } elseif (! $errors) {
            $errors[] = 'The file has no fixtures in it.';
        }

        $weeks = [];
        foreach ($dates as $week => $date) {
            $weeks[] = ['week' => $week, 'date' => $date, 'matches' => $matches[$week]];
        }

        return ['weeks' => $weeks, 'errors' => $errors];
    }

    private function date(string $value): ?CarbonImmutable
    {
        foreach (['!Y-m-d', '!d/m/Y', '!j/n/Y'] as $format) {
            try {
                $d = CarbonImmutable::createFromFormat($format, $value);
            } catch (\Throwable) {
                continue;
            }
            if ($d && $d->format(ltrim($format, '!')) === $value) {
                return $d;
            }
        }

        return null;
    }
}
