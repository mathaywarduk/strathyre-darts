<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Fixture extends Model
{
    public $timestamps = false;

    protected $fillable = ['season_id', 'league_night_id', 'player1_id', 'player2_id', 'pair_key'];

    /** A pair meets once a season, so this identifies the fixture whichever way round the names are written */
    public static function pairKey(int $a, int $b): string
    {
        return min($a, $b).'-'.max($a, $b);
    }

    public function night(): BelongsTo
    {
        return $this->belongsTo(LeagueNight::class, 'league_night_id');
    }

    public function result(): HasOne
    {
        return $this->hasOne(Result::class);
    }

    public function involves(int $playerId): bool
    {
        return $this->player1_id === $playerId || $this->player2_id === $playerId;
    }

    public function opponentOf(int $playerId): int
    {
        return $this->player1_id === $playerId ? $this->player2_id : $this->player1_id;
    }
}
