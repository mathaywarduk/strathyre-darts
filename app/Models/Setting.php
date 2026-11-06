<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** League rules. One row; null = not chosen yet. */
class Setting extends Model
{
    protected $fillable = ['win_points', 'loss_points', 'subs_pence'];

    public static function current(): self
    {
        return static::query()->first() ?? static::create([]);
    }

    /** @return list<string> Labels of anything still to be set */
    public function missing(): array
    {
        return array_values(array_filter([
            $this->win_points === null ? 'points for a win' : null,
            $this->loss_points === null ? 'points for a loss' : null,
            $this->subs_pence === null ? 'subs per league night' : null,
        ]));
    }

    public function isComplete(): bool
    {
        return $this->missing() === [];
    }
}
