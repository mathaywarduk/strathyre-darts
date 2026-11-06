<?php

namespace App\Services;

use App\Models\Player;
use Illuminate\Support\Collection;

/** Reads a players CSV (name, ranking 1-5, gender M/W) and checks it before anything is saved. */
final class PlayerImport
{
    /**
     * @param  Collection<int, Player>  $existing
     * @return array{rows: list<array{name: string, ranking: int, gender: string, action: string}>, errors: list<string>}
     */
    public function parse(string $text, Collection $existing): array
    {
        $byName = $existing->keyBy(fn (Player $p) => mb_strtolower($p->name));
        $rows = [];
        $errors = [];
        $seen = [];

        foreach (Csv::rows($text) as $n => ['line' => $line, 'cells' => $c]) {
            if ($n === 0 && strtolower($c[0] ?? '') === 'name') {
                continue; // header
            }
            $name = preg_replace('/\s+/', ' ', $c[0] ?? '');
            $ranking = $c[1] ?? '';
            $gender = strtoupper($c[2] ?? '');
            $problems = [];

            if ($name === '' || mb_strlen($name) > 120) {
                $problems[] = 'name is missing or too long';
            }
            if (! preg_match('/^[1-5]$/', $ranking)) {
                $problems[] = 'ranking must be a whole number from 1 to 5';
            }
            if (! in_array($gender, ['M', 'W'], true)) {
                $problems[] = 'gender must be M or W';
            }
            if ($name !== '' && isset($seen[mb_strtolower($name)])) {
                $problems[] = "{$name} is listed twice";
            }
            if ($problems) {
                $errors[] = "Line {$line}: ".implode('; ', $problems).'.';

                continue;
            }

            $seen[mb_strtolower($name)] = true;
            $current = $byName->get(mb_strtolower($name));
            $action = ! $current ? 'new'
                : ($current->ranking === (int) $ranking && $current->gender === $gender && $current->name === $name ? 'same' : 'update');
            $rows[] = ['name' => $name, 'ranking' => (int) $ranking, 'gender' => $gender, 'action' => $action];
        }

        if (! $rows && ! $errors) {
            $errors[] = 'The file has no players in it.';
        }

        return ['rows' => $rows, 'errors' => $errors];
    }
}
