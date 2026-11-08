<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Tournament extends Model
{
    protected $fillable = ['name', 'status', 'started_at', 'ended_at'];

    protected function casts(): array
    {
        return ['started_at' => 'datetime', 'ended_at' => 'datetime'];
    }

    /** The tournament in progress, which the home page shows instead of the league */
    public static function running(): ?self
    {
        return static::query()->where('status', 'running')->latest('id')->first();
    }

    public function entrants(): HasMany
    {
        return $this->hasMany(TournamentEntrant::class);
    }

    public function teams(): HasMany
    {
        return $this->hasMany(TournamentTeam::class);
    }

    public function matches(): HasMany
    {
        return $this->hasMany(TournamentMatch::class)->orderBy('round')->orderBy('position');
    }

    /** The winning pair, once the final has a result */
    public function champions(): ?TournamentTeam
    {
        $rounds = (int) $this->matches()->max('round');
        $final = $rounds ? $this->matches()->where('round', $rounds)->first() : null;

        return $final?->winner_team_id ? TournamentTeam::with(['entrantA', 'entrantB'])->find($final->winner_team_id) : null;
    }

    /**
     * Last year's winners: the champions of the most recent finished tournament that has any.
     *
     * @return list<string> their names
     */
    public static function lastChampionNames(): array
    {
        foreach (static::query()->where('status', 'ended')->orderByDesc('ended_at')->orderByDesc('id')->get() as $tournament) {
            if ($team = $tournament->champions()) {
                return $team->names();
            }
        }

        return [];
    }
}
