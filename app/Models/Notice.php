<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class Notice extends Model
{
    protected $fillable = ['title', 'date', 'content', 'expires_on'];

    protected function casts(): array
    {
        return ['date' => 'date', 'expires_on' => 'date'];
    }

    /** Not past its expiry date: shown up to and including that day */
    public function scopeActive(Builder $query): Builder
    {
        return $query->where(fn ($q) => $q->whereNull('expires_on')->orWhere('expires_on', '>=', today()->toDateString()));
    }

    public function isExpired(): bool
    {
        return $this->expires_on !== null && $this->expires_on->lt(today());
    }

    /** Newest first */
    public function scopeLatestFirst(Builder $query): Builder
    {
        return $query->orderByDesc('date')->orderByDesc('id');
    }
}
