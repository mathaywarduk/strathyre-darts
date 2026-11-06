<?php

namespace App\Services;

use App\Models\Fixture;
use App\Models\LeagueNight;
use App\Models\Result;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Turns pasted lines like "Callum F beat Isla C 15" into results.
 *
 * Each pair meets once a season, so a line is matched to that pair's one
 * fixture, whichever week it's in: the result counts under the fixture's own
 * week. Submitting a week closes it: fixtures from that week that have no
 * result become catch-ups.
 */
final class ResultSubmission
{
    /**
     * @return array{rows: list<array>, ok: Collection, errorCount: int, postponed: Collection}
     */
    public function preview(League $league, LeagueNight $night, string $text): array
    {
        $byName = $league->players->keyBy(fn ($p) => $this->norm($p->name));
        $byPair = $league->fixtures->keyBy(fn (Fixture $f) => Fixture::pairKey($f->player1_id, $f->player2_id));
        $max = (int) config('league.max_remaining');

        $rows = [];
        $seen = [];
        foreach (preg_split('/\r\n|\r|\n/', $text) as $raw) {
            $line = trim($raw);
            if ($line === '') {
                continue;
            }
            $row = ['line' => $line, 'error' => null, 'note' => null, 'replaces' => null];

            if (! preg_match('/^(.+?)\s+beats?\s+(.+?)\s+(\d+)$/iu', $line, $m)) {
                $rows[] = [...$row, 'error' => 'Couldn’t read this line. Write it like “Callum F beat Isla C 15”.'];

                continue;
            }
            $winner = $byName->get($this->norm($m[1]));
            $loser = $byName->get($this->norm($m[2]));
            $remaining = (int) $m[3];

            if (! $winner || ! $loser) {
                $who = ! $winner ? $m[1] : $m[2];
                $rows[] = [...$row, 'error' => "No player called “{$who}” in this season."];

                continue;
            }
            if ($winner->id === $loser->id) {
                $rows[] = [...$row, 'error' => 'A player can’t beat themselves.'];

                continue;
            }
            if ($remaining < 1 || $remaining > $max) {
                $rows[] = [...$row, 'error' => "Points left must be a whole number from 1 to {$max}."];

                continue;
            }
            $fixture = $byPair->get(Fixture::pairKey($winner->id, $loser->id));
            if (! $fixture) {
                $rows[] = [...$row, 'error' => "{$winner->name} and {$loser->name} don’t have a fixture this season."];

                continue;
            }
            if (isset($seen[$fixture->id])) {
                $rows[] = [...$row, 'error' => "{$winner->name} v {$loser->name} is already in this list."];

                continue;
            }
            $seen[$fixture->id] = true;

            $week = $fixture->night->week;
            $note = null;
            if ($week < $night->week) {
                $note = "Catch-up from week {$week}";
            } elseif ($week > $night->week) {
                $note = "Played early: week {$week} fixture";
            }
            $replaces = null;
            if ($fixture->result) {
                $old = $fixture->result;
                $replaces = $league->name($old->winner_id).' beat '.$league->name($old->loserId()).' '.$old->remaining;
            }

            $rows[] = [
                ...$row,
                'fixture' => $fixture, 'winner' => $winner, 'loser' => $loser,
                'remaining' => $remaining, 'note' => $note, 'replaces' => $replaces,
            ];
        }

        $ok = collect($rows)->filter(fn ($r) => isset($r['fixture']))->values();
        $scored = $ok->pluck('fixture.id')->all();
        $postponed = $night->isSubmitted() ? collect()
            : $league->fixturesForNight($night)->filter(fn (Fixture $f) => ! $f->result && ! in_array($f->id, $scored, true))->values();

        return [
            'rows' => $rows,
            'ok' => $ok,
            'errorCount' => count($rows) - $ok->count(),
            'postponed' => $postponed,
        ];
    }

    /** Saves the results and closes the week. The caller has checked the preview has no errors. */
    public function save(League $league, LeagueNight $night, string $text): void
    {
        $preview = $this->preview($league, $night, $text);
        abort_if($preview['errorCount'] > 0, 422, 'Some lines need fixing first.');

        DB::transaction(function () use ($preview, $night) {
            foreach ($preview['ok'] as $row) {
                Result::updateOrCreate(
                    ['fixture_id' => $row['fixture']->id],
                    ['winner_id' => $row['winner']->id, 'remaining' => $row['remaining'], 'submitted_night_id' => $night->id],
                );
            }

            if (! $night->isSubmitted()) {
                $night->update(['submitted_at' => now(), 'no_results' => $preview['ok']->isEmpty()]);
            } elseif ($night->no_results && $preview['ok']->isNotEmpty()) {
                $night->update(['no_results' => false]);
            }
        });
    }

    /** No league night happened: every fixture that week becomes a catch-up. */
    public function markNoResults(LeagueNight $night): void
    {
        abort_if($night->isSubmitted(), 422, 'That week has already been submitted.');
        $night->update(['submitted_at' => now(), 'no_results' => true]);
    }

    private function norm(string $name): string
    {
        return mb_strtolower(preg_replace('/\s+/', ' ', trim($name)));
    }
}
