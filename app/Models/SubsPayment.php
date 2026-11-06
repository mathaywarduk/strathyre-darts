<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Money handed over by a player. It's matched to league nights oldest first, never stored per week. */
class SubsPayment extends Model
{
    protected $fillable = ['season_id', 'player_id', 'amount_pence', 'paid_on'];

    protected function casts(): array
    {
        return ['paid_on' => 'date', 'amount_pence' => 'integer'];
    }

    public function player(): BelongsTo
    {
        return $this->belongsTo(Player::class);
    }
}
