<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Season extends Model
{
    protected $fillable = ['starts_on', 'status', 'max_weeks', 'weeks_off', 'opening_pence', 'closing_pence', 'ended_on', 'snapshot'];

    protected function casts(): array
    {
        return [
            'starts_on' => 'date',
            'ended_on' => 'date',
            'weeks_off' => 'array',
            'snapshot' => 'array',
        ];
    }

    public static function current(): ?self
    {
        return static::query()->where('status', 'current')->latest('id')->first();
    }

    public function isArchived(): bool
    {
        return $this->status === 'archived';
    }

    /** "2026/27": seasons run from the autumn, so a January start belongs to the year before */
    protected function label(): Attribute
    {
        return Attribute::get(function () {
            $year = $this->starts_on->month >= 7 ? $this->starts_on->year : $this->starts_on->year - 1;

            return $year.'/'.substr((string) ($year + 1), -2);
        });
    }

    public function nights(): HasMany
    {
        return $this->hasMany(LeagueNight::class)->orderBy('week');
    }

    public function fixtures(): HasMany
    {
        return $this->hasMany(Fixture::class);
    }

    public function payments(): HasMany
    {
        return $this->hasMany(SubsPayment::class);
    }

    public function expenses(): HasMany
    {
        return $this->hasMany(Expense::class)->orderBy('spent_on')->orderBy('id');
    }
}
