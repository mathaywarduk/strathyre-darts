<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Expense extends Model
{
    protected $fillable = ['season_id', 'item', 'cost_pence', 'spent_on'];

    protected function casts(): array
    {
        return ['spent_on' => 'date'];
    }
}
