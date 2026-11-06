<?php

namespace Tests\Feature;

use App\Models\Fixture;
use App\Models\Player;
use App\Models\Result;
use App\Models\Season;
use App\Models\Setting;
use App\Services\League;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\BuildsLeague;
use Tests\TestCase;

class LeagueTest extends TestCase
{
    use BuildsLeague, RefreshDatabase;

    /** Records a result straight into the database: $winner beat $loser */
    private function win(Season $season, string $winner, string $loser, int $checkout = 50): void
    {
        $w = Player::where('name', $winner)->firstOrFail();
        $l = Player::where('name', $loser)->firstOrFail();
        $fixture = Fixture::where('season_id', $season->id)->where('pair_key', Fixture::pairKey($w->id, $l->id))->firstOrFail();
        Result::create(['fixture_id' => $fixture->id, 'winner_id' => $w->id, 'checkout' => $checkout]);
    }

    private function names(Season $season): array
    {
        return (new League($season, Setting::current()))->table()->map(fn ($r) => $r['player']->name)->values()->all();
    }

    public function test_points_and_matches_played_are_counted(): void
    {
        $season = $this->liveLeague();
        [$f, $a, $b] = $this->firstMatch($this->night($season, 1));
        $this->submitLines($this->night($season, 1), "{$a} beat {$b} 121");

        $league = new League($season, Setting::current());
        $this->assertSame([2, 1, 1, 1], [$league->row($f->player1_id)['pts'], $league->row($f->player2_id)['pts'], $league->row($f->player1_id)['played'], $league->row($f->player2_id)['played']]);
        $this->assertSame(121, $league->highestCheckout($f->player1_id));
        $this->assertNull($league->highestCheckout($f->player2_id), 'a loser has no checkout');
    }

    public function test_highest_checkout_is_the_best_of_a_players_wins(): void
    {
        $season = $this->liveLeague();
        $this->win($season, 'Tess T', 'Alice A', 60);
        $this->win($season, 'Tess T', 'Bob B', 164);
        $this->win($season, 'Tess T', 'Cara C', 32);

        $league = new League($season, Setting::current());
        $this->assertSame(164, $league->highestCheckout(Player::where('name', 'Tess T')->value('id')));
    }

    public function test_the_table_has_no_score_difference_and_ranks_by_points(): void
    {
        $season = $this->liveLeague();
        $this->win($season, 'Tess T', 'Alice A');
        $this->win($season, 'Tess T', 'Bob B');

        $names = $this->names($season);
        $this->assertSame('Tess T', $names[0]);
        $this->get('/')->assertDontSee('+/-', false)->assertSee('Pts');
    }

    public function test_players_level_on_points_are_split_by_who_won_head_to_head(): void
    {
        $season = $this->liveLeague();
        // Tess and Alice both end on 3 points (a win and a loss). Tess beat Alice, though Alice is first alphabetically.
        $this->win($season, 'Tess T', 'Alice A');
        $this->win($season, 'Bob B', 'Tess T');
        $this->win($season, 'Alice A', 'Cara C');

        $names = $this->names($season);
        $this->assertLessThan(array_search('Alice A', $names), array_search('Tess T', $names));
    }

    public function test_level_players_who_have_not_met_go_alphabetically(): void
    {
        $season = $this->liveLeague();
        $this->win($season, 'Tess T', 'Dan D');
        $this->win($season, 'Bob B', 'Eve E');

        $names = $this->names($season);
        $this->assertSame(['Bob B', 'Tess T'], array_slice($names, 0, 2), 'level on points, never played each other');
    }

    public function test_three_level_players_are_ordered_by_their_wins_against_each_other(): void
    {
        $season = $this->liveLeague();
        Setting::current()->update(['loss_points' => 0]); // so only wins count
        // Tess beat Sam and Rae; Sam beat Rae. Then each gets one more win elsewhere so all three have 4 points.
        $this->win($season, 'Tess T', 'Sam S');
        $this->win($season, 'Tess T', 'Rae R');
        $this->win($season, 'Sam S', 'Rae R');
        $this->win($season, 'Sam S', 'Alice A');
        $this->win($season, 'Rae R', 'Bob B');
        $this->win($season, 'Rae R', 'Cara C');

        $names = $this->names($season);
        $this->assertSame(['Tess T', 'Sam S', 'Rae R'], array_slice($names, 0, 3), 'Rae R would be first alphabetically');
    }

    public function test_a_circle_of_wins_is_alphabetical_during_the_season_and_a_tie_at_the_end(): void
    {
        $season = $this->liveLeague(5);
        Setting::current()->update(['loss_points' => 0]);
        $this->win($season, 'Alice A', 'Bob B');
        $this->win($season, 'Bob B', 'Cara C');
        $this->win($season, 'Cara C', 'Alice A');

        $league = new League($season, Setting::current());
        $this->assertFalse($league->isComplete());
        $this->assertSame(['Alice A', 'Bob B', 'Cara C'], array_slice($this->names($season), 0, 3));
        $this->assertSame([1, 2, 3], $league->table()->take(3)->pluck('pos')->values()->all());
        $this->assertFalse($league->table()->first()['tied']);
    }

    public function test_players_who_cannot_be_split_share_a_position_once_every_match_is_played(): void
    {
        $season = $this->liveLeague(3);
        $this->win($season, 'Alice A', 'Bob B');
        $this->win($season, 'Bob B', 'Cara C');
        $this->win($season, 'Cara C', 'Alice A');

        $league = new League($season, Setting::current());
        $this->assertTrue($league->isComplete());
        $this->assertSame([1, 1, 1], $league->table()->pluck('pos')->values()->all());
        $this->assertTrue($league->table()->every(fn ($r) => $r['tied']));
        $this->get('/')->assertSee('1=')->assertSee('will play off');
    }

    public function test_head_to_head_settles_pairs_even_when_the_season_is_complete(): void
    {
        $season = $this->liveLeague(4);
        // Alice and Bob finish level on 5 points, Cara and Dan on 4. Bob beat Alice and Dan beat Cara,
        // so each pair goes the opposite way to alphabetical, and nothing is a tie.
        $this->win($season, 'Bob B', 'Alice A');
        $this->win($season, 'Bob B', 'Dan D');
        $this->win($season, 'Cara C', 'Bob B');
        $this->win($season, 'Alice A', 'Cara C');
        $this->win($season, 'Alice A', 'Dan D');
        $this->win($season, 'Dan D', 'Cara C');

        $league = new League($season, Setting::current());
        $this->assertTrue($league->isComplete());
        $this->assertSame(['Bob B', 'Alice A', 'Dan D', 'Cara C'], $this->names($season));
        $this->assertSame([1, 2, 3, 4], $league->table()->pluck('pos')->values()->all());
        $this->assertFalse($league->table()->contains('tied', true));
    }

    public function test_the_league_shows_its_highest_checkout(): void
    {
        $season = $this->liveLeague();
        $this->get('/')->assertDontSee('Highest checkout');

        $this->win($season, 'Tess T', 'Alice A', 60);
        $this->win($season, 'Bob B', 'Cara C', 164);
        $this->get('/')->assertSee('Highest checkout')->assertSee('164')->assertSee('Bob B')->assertDontSee('(week 1)Tess', false);

        $best = (new League($season, Setting::current()))->bestCheckout();
        $this->assertSame(164, $best['checkout']);
        $this->assertCount(1, $best['fixtures']);

        $this->win($season, 'Dan D', 'Eve E', 164); // level: both are shown
        $this->assertCount(2, (new League($season, Setting::current()))->bestCheckout()['fixtures']);
    }

    public function test_leaders_are_the_top_woman_and_top_man_once_they_have_played(): void
    {
        $season = $this->liveLeague();
        $league = new League($season, Setting::current());
        $this->assertSame(['W' => null, 'M' => null], $league->leaders(), 'nobody leads before a match is played');

        [$f, $a, $b] = $this->firstMatch($this->night($season, 1));
        $this->submitLines($this->night($season, 1), "{$a} beat {$b} 40");
        $leaders = (new League($season, Setting::current()))->leaders();

        $winner = Player::find($f->player1_id);
        $this->assertSame($winner->id, $leaders[$winner->gender]);
    }

    public function test_postponed_matches_become_catch_ups_when_a_week_is_submitted(): void
    {
        $season = $this->liveLeague();
        $night = $this->night($season, 1);
        [$f, $a, $b] = $this->firstMatch($night);
        $this->submitLines($night, "{$a} beat {$b} 121");

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
        $this->submitLines($this->night($season, 1), "{$a} beat {$b} 121");
        auth()->logout();

        $this->get('/')->assertSee('Week 2')->assertDontSee('Results pending')
            ->assertSee('1 of 19 weeks played, 9 catch-ups to play')->assertSeeInOrder(['Checkout:', '121']);
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
        $this->submitLines($this->night($season, 1), "{$a} beat {$b} 121");

        auth()->logout();
        $this->get(route('players.show', $f->player1_id))->assertOk()
            ->assertSee('Next match')->assertSee('W (121)')->assertSee('Highest checkout')->assertSee('Subs');
        $this->get(route('players.show', $f->player2_id))->assertSee('>L<', false);

        // Someone whose week 1 match wasn't played has a catch-up panel
        $other = $season->fixtures()->where('league_night_id', $this->night($season, 1)->id)->where('id', '!=', $f->id)->first();
        $this->get(route('players.show', $other->player1_id))->assertSee('Catch-up from week 1');
    }
}
