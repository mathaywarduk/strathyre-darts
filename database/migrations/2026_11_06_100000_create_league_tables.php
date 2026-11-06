<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // One row. Null means "not set yet"; the league can't go live until all three are set.
        Schema::create('settings', function (Blueprint $table) {
            $table->id();
            $table->unsignedTinyInteger('win_points')->nullable();
            $table->unsignedTinyInteger('loss_points')->nullable();
            $table->unsignedInteger('subs_pence')->nullable();
            $table->timestamps();
        });

        Schema::create('seasons', function (Blueprint $table) {
            $table->id();
            $table->date('starts_on');
            $table->string('status', 10)->default('current')->index(); // current | archived
            $table->unsignedTinyInteger('max_weeks')->nullable();
            $table->json('weeks_off')->nullable();
            $table->integer('opening_pence')->default(0);
            $table->integer('closing_pence')->nullable();
            $table->date('ended_on')->nullable();
            $table->json('snapshot')->nullable(); // final table and results, with names as they were
            $table->timestamps();
        });

        Schema::create('players', function (Blueprint $table) {
            $table->id();
            $table->string('name')->unique();
            $table->unsignedTinyInteger('ranking');
            $table->char('gender', 1); // M | W
            $table->timestamps();
        });

        Schema::create('league_nights', function (Blueprint $table) {
            $table->id();
            $table->foreignId('season_id')->constrained()->cascadeOnDelete();
            $table->unsignedSmallInteger('week');
            $table->date('date');
            $table->timestamp('submitted_at')->nullable();
            $table->boolean('no_results')->default(false);
            $table->unique(['season_id', 'week']);
        });

        Schema::create('fixtures', function (Blueprint $table) {
            $table->id();
            $table->foreignId('season_id')->constrained()->cascadeOnDelete();
            $table->foreignId('league_night_id')->constrained()->cascadeOnDelete();
            $table->foreignId('player1_id')->constrained('players');
            $table->foreignId('player2_id')->constrained('players');
            $table->string('pair_key', 20); // "3-7": a pair meets once a season
            $table->unique(['season_id', 'pair_key']);
        });

        Schema::create('results', function (Blueprint $table) {
            $table->id();
            $table->foreignId('fixture_id')->unique()->constrained()->cascadeOnDelete();
            $table->foreignId('winner_id')->constrained('players');
            $table->unsignedSmallInteger('remaining'); // points the loser had left
            $table->foreignId('submitted_night_id')->nullable()->constrained('league_nights')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('subs_payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('season_id')->constrained()->cascadeOnDelete();
            $table->foreignId('player_id')->constrained('players')->cascadeOnDelete();
            $table->foreignId('league_night_id')->constrained()->cascadeOnDelete();
            $table->timestamps();
            $table->unique(['player_id', 'league_night_id']);
        });

        Schema::create('expenses', function (Blueprint $table) {
            $table->id();
            $table->foreignId('season_id')->constrained()->cascadeOnDelete();
            $table->string('item');
            $table->unsignedInteger('cost_pence');
            $table->date('spent_on');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        foreach (['expenses', 'subs_payments', 'results', 'fixtures', 'league_nights', 'players', 'seasons', 'settings'] as $t) {
            Schema::dropIfExists($t);
        }
    }
};
