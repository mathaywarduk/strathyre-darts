<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // The yearly doubles knockout. status: running (shown instead of the home page) or ended.
        Schema::create('tournaments', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('status', 10)->default('running')->index();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('ended_at')->nullable();
            $table->timestamps();
        });

        // Who turned up: league players, or anyone else by name
        Schema::create('tournament_entrants', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tournament_id')->constrained()->cascadeOnDelete();
            $table->foreignId('player_id')->nullable()->constrained('players')->nullOnDelete();
            $table->string('name');
            $table->boolean('defending')->default(false); // part of last year's winning pair
            $table->unique(['tournament_id', 'name']);
        });

        Schema::create('tournament_teams', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tournament_id')->constrained()->cascadeOnDelete();
            $table->foreignId('entrant_a_id')->constrained('tournament_entrants')->cascadeOnDelete();
            $table->foreignId('entrant_b_id')->constrained('tournament_entrants')->cascadeOnDelete();
            $table->boolean('defending')->default(false); // last year's champions, kept together
        });

        // Round 1 is the first round, the highest round is the final. Match (r, p) feeds match (r + 1, ceil(p / 2)).
        Schema::create('tournament_matches', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tournament_id')->constrained()->cascadeOnDelete();
            $table->unsignedTinyInteger('round');
            $table->unsignedSmallInteger('position');
            $table->foreignId('team_a_id')->nullable()->constrained('tournament_teams')->nullOnDelete();
            $table->foreignId('team_b_id')->nullable()->constrained('tournament_teams')->nullOnDelete();
            $table->foreignId('winner_team_id')->nullable()->constrained('tournament_teams')->nullOnDelete();
            $table->unsignedSmallInteger('checkout')->nullable();
            $table->boolean('is_bye')->default(false);
            $table->unique(['tournament_id', 'round', 'position']);
        });
    }

    public function down(): void
    {
        foreach (['tournament_matches', 'tournament_teams', 'tournament_entrants', 'tournaments'] as $t) {
            Schema::dropIfExists($t);
        }
    }
};
