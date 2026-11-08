<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TournamentEntrant extends Model
{
    public $timestamps = false;

    protected $fillable = ['tournament_id', 'player_id', 'name', 'defending'];

    protected function casts(): array
    {
        return ['defending' => 'boolean'];
    }
}
