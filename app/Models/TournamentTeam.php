<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TournamentTeam extends Model
{
    public $timestamps = false;

    protected $fillable = ['tournament_id', 'entrant_a_id', 'entrant_b_id', 'defending'];

    protected function casts(): array
    {
        return ['defending' => 'boolean'];
    }

    public function entrantA(): BelongsTo
    {
        return $this->belongsTo(TournamentEntrant::class, 'entrant_a_id');
    }

    public function entrantB(): BelongsTo
    {
        return $this->belongsTo(TournamentEntrant::class, 'entrant_b_id');
    }

    /** @return array{0: string, 1: string} */
    public function names(): array
    {
        return [$this->entrantA->name, $this->entrantB->name];
    }

    public function label(): string
    {
        return implode(' & ', $this->names());
    }
}
