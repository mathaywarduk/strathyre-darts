<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Result extends Model
{
    protected $fillable = ['fixture_id', 'winner_id', 'checkout', 'submitted_night_id'];

    public function fixture(): BelongsTo
    {
        return $this->belongsTo(Fixture::class);
    }

    public function loserId(): int
    {
        return $this->fixture->opponentOf($this->winner_id);
    }
}
