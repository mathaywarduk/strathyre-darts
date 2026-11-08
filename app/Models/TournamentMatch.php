<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TournamentMatch extends Model
{
    public $timestamps = false;

    protected $fillable = ['tournament_id', 'round', 'position', 'team_a_id', 'team_b_id', 'winner_team_id', 'checkout', 'is_bye'];

    protected function casts(): array
    {
        return ['is_bye' => 'boolean'];
    }

    /** Both teams are known and nobody has been given a free pass: it can be played */
    public function isReady(): bool
    {
        return ! $this->is_bye && $this->team_a_id !== null && $this->team_b_id !== null;
    }

    public function isPlayed(): bool
    {
        return $this->winner_team_id !== null;
    }
}
