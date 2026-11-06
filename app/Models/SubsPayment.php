<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SubsPayment extends Model
{
    protected $fillable = ['season_id', 'player_id', 'league_night_id'];
}
