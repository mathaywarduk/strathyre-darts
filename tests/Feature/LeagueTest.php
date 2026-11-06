<?php

namespace Tests\Feature;

use App\Models\Player;
use App\Models\Setting;
use App\Services\League;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\BuildsLeague;
use Tests\TestCase;

class LeagueTest extends TestCase
{
    use BuildsLeague, RefreshDatabase;

    public function test_score_difference_adds_the_points_left_for_a_win_and_subtracts_them_for_a_loss(): void
    {
        $season = $this->liveLeague();
        [$f, $a, $b] = $this->firstMatch($this->night($season, 1));
        $this->submitLines($this->night($season, 1), "{$a} beat {$b} 15");

        $league = new League($season, Setting::current());
        $this->assertSame(15, $league->row($f->player1_id)['diff']);
        $this->assertSame(-15, $league->row($f->player2_id)['diff']);
        $this->assertSame(2, $league->row($f->player1_id)['pts']);
        $this->assertSame(1, $league->row($f->player2_id)['pts']);
        $this->assertSame(1, $league->row($f->player2_id)['played']);
    }

    public function test_table_sorts_by_points_then_difference_then_name(): void
    {
        $season = $this->liveLeague();
        $fixtures = $season->fixtures()->where('league_night_id', $this->night($season, 1)->id)->orderBy('id')->get();
        $names = fn ($f) => [Player::find($f->player1_id)->name, Player::find($f->player2_id)->name];

        // Two winners on equal points: the bigger difference is higher
        [$w1, $l1] = $names($fixtures[0]);
        [$w2, $l2] = $names($fixtures[1]);
        $this->submitLines($this->night($season, 1), "{$w1} beat {$l1} 20\n{$w2} beat {$l2} 90");

        $table = (new League($season, Setting::current()))->table()->values();
        $this->assertSame($w2, $table[0]['player']->name);
        $this->assertSame($w1, $table[1]['player']->name);
        // On the same points (the losers, 1 point) the bigger loss is lower; beyond that, alphabetical
        $this->assertSame(1, $table[2]['pts']);
        $this->assertSame($l1, $table[2]['player']->name); // -20 beats -90
        $this->assertSame($l2, $table[3]['player']->name);
        // Everyone else is on 0 points, alphabetical
        $zeros = $table->filter(fn ($r) => $r['pts'] === 0)->pluck('player.name')->all();
        $sorted = $zeros;
        usort($sorted, 'strcasecmp');
        $this->assertSame($sorted, $zeros);
    }

    public function test_leaders_are_the_top_woman_and_top_man_once_they_have_played(): void
    {
        $season = $this->liveLeague();
        $league = new League($season, Setting::current());
        $this->assertSame(['W' => null, 'M' => null], $league->leaders(), 'nobody leads before a match is played');

        [$f, $a, $b] = $this->firstMatch($this->night($season, 1));
        $this->submitLines($this->night($season, 1), "{$a} beat {$b} 10");
        $leaders = (new League($season, Setting::current()))->leaders();

        $winner = Player::find($f->player1_id);
        $this->assertSame($winner->id, $leaders[$winner->gender]);
    }

    public function test_postponed_matches_become_catch_ups_when_a_week_is_submitted(): void
    {
        $season = $this->liveLeague();
        $night = $this->night($season, 1);
        [$f, $a, $b] = $this->firstMatch($night);
        $this->submitLines($night, "{$a} beat {$b} 15");

        $league = new League($season, Setting::current());
        $this->assertCount(9, $league->catchUps());
        $this->assertSame(2, $league->currentNight()->week);
        $this->assertSame(1, $league->weeksPlayed());
    }

    public function test_a_catch_up_is_matched_to_its_one_fixture_whatever_week_it_is_entered_in(): void
    {
        $season = $this->liveLeague();
        $week1 = $this->night($season, 1);
        $this->submitLines($week1, ''); // nothing played: all ten are catch-ups
        [$f, $a, $b] = $this->firstMatch($week1);

        // Entered with week 2's results, in lower case
        $this->submitLines($this->night($season, 2), strtolower("{$b} beat {$a} 33"));

        $result = $f->fresh()->result;
        $this->assertSame($f->player2_id, $result->winner_id);
        $this->assertSame($this->night($season, 2)->id, $result->submitted_night_id);
        // Week 1's other nine plus all ten of week 2's, which weren't played either
        $this->assertCount(19, (new League($season, Setting::current()))->catchUps());
    }

    public function test_results_pending_shows_after_ten_on_league_night(): void
    {
        $season = $this->liveLeague();
        $league = fn () => new League($season, Setting::current());

        $this->assertFalse($league()->resultsPending(now()->setDate(2026, 11, 13)->setTime(21, 59)));
        $this->assertTrue($league()->resultsPending(now()->setDate(2026, 11, 13)->setTime(22, 0)));
        $this->assertTrue($league()->resultsPending(now()->setDate(2026, 11, 14)->setTime(9, 0)));
        $this->assertFalse($league()->resultsPending(now()->setDate(2026, 11, 10)->setTime(23, 0)));
    }

    public function test_home_page_shows_the_week_the_tag_and_progress(): void
    {
        $season = $this->liveLeague();
        $this->travelTo('2026-11-13 22:30');

        $this->get('/')->assertOk()->assertSee('Week 1')->assertSee('Friday 13 November')
            ->assertSee('Results pending')->assertSee('0 of 19 weeks played, no catch-ups to play');

        [$f, $a, $b] = $this->firstMatch($this->night($season, 1));
        $this->submitLines($this->night($season, 1), "{$a} beat {$b} 15");
        auth()->logout();

        $this->get('/')->assertSee('Week 2')->assertDontSee('Results pending')
            ->assertSee('1 of 19 weeks played, 9 catch-ups to play')->assertSee('+15');
    }

    public function test_the_league_is_not_live_until_settings_and_fixtures_exist(): void
    {
        $this->get('/')->assertOk()->assertSee('Coming soon');
        $this->liveLeague();
        $this->get('/')->assertDontSee('Coming soon');

        Setting::current()->update(['win_points' => null]);
        $this->get('/')->assertSee('Coming soon');
    }

    public function test_player_page_shows_stats_next_match_catch_ups_and_results(): void
    {
        $season = $this->liveLeague();
        [$f, $a, $b] = $this->firstMatch($this->night($season, 1));
        $this->submitLines($this->night($season, 1), "{$a} beat {$b} 15");

        auth()->logout();
        $this->get(route('players.show', $f->player1_id))->assertOk()
            ->assertSee('Next match')->assertSee('W (+15)')->assertDontSee('Subs');
        $this->get(route('players.show', $f->player2_id))->assertSee('L (-15)');

        // Someone whose week 1 match wasn't played has a catch-up panel
        $other = $season->fixtures()->where('league_night_id', $this->night($season, 1)->id)->where('id', '!=', $f->id)->first();
        $this->get(route('players.show', $other->player1_id))->assertSee('Catch-up from week 1');
    }
}
