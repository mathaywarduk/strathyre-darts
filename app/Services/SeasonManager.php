<?php

namespace App\Services;

use App\Models\Season;
use App\Models\Setting;
use Illuminate\Support\Facades\DB;

final class SeasonManager
{
    /**
     * End the current season: freeze its final table and results (with names as
     * they are now), carry the money balance forward, and open a new season.
     */
    public function reset(Season $current, \DateTimeInterface $newStart): Season
    {
        return DB::transaction(function () use ($current, $newStart) {
            $settings = Setting::current();
            $league = new League($current, $settings);
            $finance = new Finance($league);

            $table = $league->table()->map(fn ($r) => [
                'pos' => $r['pos'], 'name' => $r['player']->name, 'gender' => $r['player']->gender,
                'played' => $r['played'], 'diff' => $r['diff'], 'pts' => $r['pts'],
            ])->values()->all();

            $weeks = $league->nights->map(fn ($night) => [
                'week' => $night->week,
                'date' => $night->date->toDateString(),
                'no_results' => $night->no_results,
                'results' => $league->fixturesForNight($night)->filter(fn ($f) => $f->result)->map(fn ($f) => [
                    'winner' => $league->name($f->result->winner_id),
                    'loser' => $league->name($f->result->loserId()),
                    'remaining' => $f->result->remaining,
                ])->values()->all(),
                'unplayed' => $league->fixturesForNight($night)->filter(fn ($f) => ! $f->result)->map(fn ($f) => [
                    $league->name($f->player1_id), $league->name($f->player2_id),
                ])->values()->all(),
            ])->all();

            $current->update([
                'status' => 'archived',
                'ended_on' => today(),
                'closing_pence' => $finance->balance(),
                'snapshot' => ['table' => $table, 'weeks' => $weeks],
            ]);

            return Season::create([
                'starts_on' => $newStart,
                'status' => 'current',
                'opening_pence' => $finance->balance(),
            ]);
        });
    }

    /** Permanently removes a season and everything in it. */
    public function delete(Season $season): void
    {
        DB::transaction(function () use ($season) {
            DB::table('subs_payments')->where('season_id', $season->id)->delete();
            DB::table('expenses')->where('season_id', $season->id)->delete();
            DB::table('results')->whereIn('fixture_id', DB::table('fixtures')->where('season_id', $season->id)->select('id'))->delete();
            DB::table('fixtures')->where('season_id', $season->id)->delete();
            DB::table('league_nights')->where('season_id', $season->id)->delete();
            $season->delete();
        });
    }
}
