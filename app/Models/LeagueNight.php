<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class LeagueNight extends Model
{
    public $timestamps = false;

    protected $fillable = ['season_id', 'week', 'date', 'submitted_at', 'no_results'];

    protected function casts(): array
    {
        return ['date' => 'date', 'submitted_at' => 'datetime', 'no_results' => 'boolean'];
    }

    public function isSubmitted(): bool
    {
        return $this->submitted_at !== null;
    }

    public function fixtures(): HasMany
    {
        return $this->hasMany(Fixture::class);
    }
}
