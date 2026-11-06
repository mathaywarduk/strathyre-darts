<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // A result records the winner's checkout, not the points the loser had left
        Schema::table('results', function (Blueprint $table) {
            $table->renameColumn('remaining', 'checkout');
        });

        // Subs are payments of an amount, not a tick per week. Existing weekly ticks
        // become payments of one night's subs, so nothing recorded is lost.
        $rate = (int) (DB::table('settings')->value('subs_pence') ?? 0);
        $old = DB::table('subs_payments')->get(['season_id', 'player_id', 'created_at']);

        Schema::dropIfExists('subs_payments');
        Schema::create('subs_payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('season_id')->constrained()->cascadeOnDelete();
            $table->foreignId('player_id')->constrained('players')->cascadeOnDelete();
            $table->unsignedInteger('amount_pence');
            $table->date('paid_on');
            $table->timestamps();
        });

        foreach ($old as $row) {
            DB::table('subs_payments')->insert([
                'season_id' => $row->season_id,
                'player_id' => $row->player_id,
                'amount_pence' => $rate,
                'paid_on' => substr((string) $row->created_at, 0, 10) ?: now()->toDateString(),
                'created_at' => $row->created_at,
                'updated_at' => $row->created_at,
            ]);
        }
    }

    public function down(): void
    {
        Schema::table('results', function (Blueprint $table) {
            $table->renameColumn('checkout', 'remaining');
        });

        Schema::dropIfExists('subs_payments');
        Schema::create('subs_payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('season_id')->constrained()->cascadeOnDelete();
            $table->foreignId('player_id')->constrained('players')->cascadeOnDelete();
            $table->foreignId('league_night_id')->constrained()->cascadeOnDelete();
            $table->timestamps();
            $table->unique(['player_id', 'league_night_id']);
        });
    }
};
