<?php

namespace Tests\Feature;

use App\Models\Fixture;
use App\Models\Player;
use App\Models\Result;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\BuildsLeague;
use Tests\TestCase;

class HomeTest extends TestCase
{
    use BuildsLeague, RefreshDatabase;

    private function win($season, string $winner, string $loser, int $checkout): void
    {
        $w = Player::where('name', $winner)->firstOrFail();
        $l = Player::where('name', $loser)->firstOrFail();
        $fixture = Fixture::where('season_id', $season->id)->where('pair_key', Fixture::pairKey($w->id, $l->id))->firstOrFail();
        Result::create(['fixture_id' => $fixture->id, 'winner_id' => $w->id, 'checkout' => $checkout]);
    }

    public function test_the_home_page_starts_with_the_league_table_not_a_big_week_heading(): void
    {
        $this->liveLeague();
        $this->travelTo('2026-11-14 10:00');

        $this->get('/')->assertOk()->assertDontSee('hero-big', false)->assertDontSee('Next week')
            ->assertSeeInOrder(['League table', '0 of 19 weeks played']);
    }

    public function test_the_sections_run_table_results_link_checkout_week_then_catch_ups(): void
    {
        $season = $this->liveLeague();
        $this->travelTo('2026-11-14 10:00');
        $this->win($season, 'Bob B', 'Cara C', 164);
        $night = $this->night($season, 1);
        $night->update(['submitted_at' => now()]);

        $this->get('/')->assertSeeInOrder(['League table', 'Highest checkout', 'Week 2', 'Catch-up fixtures'])
            ->assertDontSee('All results')->assertDontSee('Last week’s results');
    }

    public function test_the_week_heading_is_the_current_week_with_its_date(): void
    {
        $this->liveLeague();
        $this->travelTo('2026-11-13 22:30');

        $this->get('/')->assertSee('Week 1')->assertSee('Friday 13 November')->assertSee('Results pending');
    }

    public function test_the_home_page_no_longer_lists_results(): void
    {
        $season = $this->liveLeague();
        [$f, $a, $b] = $this->firstMatch($this->night($season, 1));
        $this->submitLines($this->night($season, 1), "{$a} beat {$b} 121");
        auth()->logout();

        $this->get('/')->assertDontSee('Checkout:')->assertDontSee('>Edit<', false)->assertDontSee('All results');
    }

    public function test_the_results_page_lists_every_result_by_week_newest_first(): void
    {
        $season = $this->liveLeague();
        foreach ([1, 3, 2] as $week) {
            $fixture = Fixture::where('season_id', $season->id)->where('league_night_id', $this->night($season, $week)->id)->orderBy('id')->first();
            Result::create(['fixture_id' => $fixture->id, 'winner_id' => $fixture->player1_id, 'checkout' => 100 + $week]);
        }

        $html = $this->get('/results')->assertOk()->assertSee('Checkout:')->getContent();
        $this->assertSame(3, substr_count($html, '>beat<'));
        $this->assertTrue(strpos($html, 'Week 3') < strpos($html, 'Week 2') && strpos($html, 'Week 2') < strpos($html, 'Week 1'), 'newest week first');
        $this->assertStringNotContainsString('Week 4', $html, 'weeks with no results are left out');
    }

    public function test_a_catch_up_played_later_shows_under_its_own_week(): void
    {
        $season = $this->liveLeague();
        $week1 = $this->night($season, 1);
        $this->submitLines($week1, ''); // week 1 not played
        [$f, $a, $b] = $this->firstMatch($week1);
        $this->submitLines($this->night($season, 2), "{$a} beat {$b} 77"); // played with week 2's results

        $html = $this->get('/results')->getContent();
        $this->assertStringContainsString('<h2 class="h2">Week 1</h2>', $html);
        $this->assertStringNotContainsString('<h2 class="h2">Week 2</h2>', $html, 'week 2 had no results of its own');
        $this->assertStringContainsString('77', $html);
    }

    public function test_the_results_page_needs_a_live_league(): void
    {
        $this->get('/results')->assertRedirect(route('home'));
    }

    public function test_edit_buttons_are_on_the_results_page_for_admins_only(): void
    {
        $season = $this->liveLeague();
        $this->win($season, 'Tess T', 'Alice A', 60);

        $this->get('/results')->assertDontSee('>Edit<', false);
        $this->actingAs($this->admin());
        $this->get('/results')->assertSee('>Edit<', false);
    }

    public function test_when_the_season_is_over_home_says_so_in_the_table_line(): void
    {
        $season = $this->liveLeague(3);
        foreach ($season->nights as $night) {
            $night->update(['submitted_at' => now()]);
        }
        $this->win($season, 'Alice A', 'Bob B', 50);
        $this->win($season, 'Bob B', 'Cara C', 50);
        $this->win($season, 'Cara C', 'Alice A', 50);

        $this->get('/')->assertSee('All 3 weeks played')->assertDontSee('hero-big', false);
    }

    public function test_results_is_a_tab_in_the_nav_once_the_league_is_live(): void
    {
        $this->get('/')->assertDontSee(route('results.index'));

        $this->liveLeague();
        $this->get('/')->assertSee('href="'.route('results.index').'"', false)->assertSee('>Results</a>', false);
        $this->get('/results')->assertSee('aria-current="page" >Results</a>', false);
    }

    public function test_the_highest_checkout_is_a_number_in_a_circle_with_the_name_beside_it(): void
    {
        $season = $this->liveLeague();
        $this->win($season, 'Bob B', 'Cara C', 164);

        $html = $this->get('/')->getContent();
        $this->assertMatchesRegularExpression('/class="cc-circle">164<\/div>\s*<div class="cc-text">\s*<div class="cc-label">Highest checkout<\/div>\s*<div class="cc-who"><a [^>]*>Bob B<\/a>/', $html);
    }

    public function test_the_results_page_says_when_a_weeks_results_have_not_been_entered_yet(): void
    {
        $season = $this->liveLeague();

        $this->travelTo('2026-11-12 12:00'); // the day before week 1
        $this->get('/results')->assertSee('No results yet')->assertDontSee('haven’t been entered yet');

        $this->travelTo('2026-11-13 21:00'); // league night, before the cut-off
        $this->get('/results')->assertDontSee('haven’t been entered yet');

        $this->travelTo('2026-11-13 22:30');
        $this->get('/results')->assertSee('Week 1 results (Friday 13 November) haven’t been entered yet.')->assertDontSee('Enter them');

        $this->travelTo('2026-11-14 09:00');
        $this->actingAs($this->admin());
        $this->get('/results')->assertSee('haven’t been entered yet')->assertSee(route('admin.results.index'));

        // Entered: the note goes
        [$f, $a, $b] = $this->firstMatch($this->night($season, 1));
        $this->submitLines($this->night($season, 1), "{$a} beat {$b} 121");
        $this->get('/results')->assertDontSee('haven’t been entered yet')->assertSee('Week 1');
    }

    public function test_the_note_sits_above_older_weeks_that_do_have_results(): void
    {
        $season = $this->liveLeague();
        $this->travelTo('2026-11-14 09:00');
        [$f, $a, $b] = $this->firstMatch($this->night($season, 1));
        $this->submitLines($this->night($season, 1), "{$a} beat {$b} 121");

        $this->travelTo('2026-11-21 09:00'); // week 2 has come and gone with nothing entered
        auth()->logout();
        $html = $this->get('/results')->getContent();

        $this->assertStringContainsString('Week 2 results', $html);
        $this->assertLessThan(strpos($html, '<h2 class="h2">Week 1</h2>'), strpos($html, 'Week 2 results'));
    }

    public function test_the_public_pages_have_their_desktop_layout_hooks(): void
    {
        $season = $this->liveLeague();
        $this->travelTo('2026-11-21 09:00');
        $this->win($season, 'Bob B', 'Cara C', 100);

        // Home: the table beside a side column; the rest stack on a phone
        $html = $this->get('/')->getContent();
        $this->assertStringContainsString('class="split"', $html);
        $this->assertStringContainsString('class="split-main"', $html);
        $this->assertStringContainsString('class="split-side"', $html);
        $this->assertLessThan(strpos($html, 'split-side'), strpos($html, 'League table'));
        $this->assertGreaterThan(strpos($html, 'split-side'), strpos($html, 'Catch-up fixtures'));

        // Results and subs received stay a single column
        $this->get('/results')->assertSee('class="narrow"', false)->assertDontSee('cols-2', false);
        $this->get('/finance')->assertSee('class="fin-top"', false);
        $this->get('/finance/subs-received')->assertSee('class="narrow"', false)->assertDontSee('cols-2', false);
        $this->get('/finance/subs-owed')->assertSee('class="cols-2"', false);
        $this->get('/finance/weeks/1')->assertSee('class="grid-2"', false);
        $this->get(route('players.show', Player::where('name', 'Bob B')->value('id')))->assertSee('class="pcols"', false);
    }

    public function test_the_player_page_keeps_its_phone_order_in_the_markup_order_of_each_column(): void
    {
        $season = $this->liveLeague();
        $html = $this->get(route('players.show', Player::where('name', 'Bob B')->value('id')))->getContent();

        // Left column: stats, next match, subs. Right column: fixtures, results.
        $order = ['pc-stats', 'pc-panels', 'pc-subs', 'pc-fixtures', 'pc-results'];
        $positions = array_map(fn ($c) => strpos($html, 'class="'.($c === 'pc-stats' ? $c : ($c === 'pc-panels' ? 'panels '.$c : 'sec '.$c))), $order);
        $this->assertSame($positions, collect($positions)->sort()->values()->all());
    }

    public function test_the_admin_keeps_its_own_width(): void
    {
        $this->actingAs($this->admin());
        $this->get(route('admin.settings.edit'))->assertSee('class="wrap wide"', false)->assertDontSee('admin-grid', false);
    }
}
