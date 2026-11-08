<?php

namespace Tests\Feature;

use App\Models\Fixture;
use App\Models\Player;
use App\Models\Result;
use App\Models\Season;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\BuildsLeague;
use Tests\TestCase;

/** The Results and fixtures page: one week at a time. League nights are Fridays from 13 Nov 2026. */
class ResultsAndFixturesTest extends TestCase
{
    use BuildsLeague, RefreshDatabase;

    private function play($season, int $week, int $checkout = 100): Fixture
    {
        $fixture = Fixture::where('season_id', $season->id)->where('league_night_id', $this->night($season, $week)->id)->orderBy('id')->first();
        Result::create(['fixture_id' => $fixture->id, 'winner_id' => $fixture->player1_id, 'checkout' => $checkout]);

        return $fixture;
    }

    private function shownWeek(string $url = '/results'): int
    {
        preg_match('/<h2 class="h2" style="margin: 0">Week (\d+)<\/h2>/', $this->get($url)->getContent(), $m);

        return (int) $m[1];
    }

    public function test_the_page_is_called_results_and_fixtures(): void
    {
        $this->liveLeague();
        $this->get('/results')->assertOk()->assertSee('Results and fixtures')->assertSee('>Results and fixtures</a>', false);
    }

    /* ---- which week it opens on: the Monday to Sunday that holds league night ---- */

    public function test_it_opens_on_this_weeks_league_night_from_monday(): void
    {
        $this->liveLeague();

        $this->travelTo('2026-11-09 09:00'); // Monday, Friday 13th is week 1
        $this->assertSame(1, $this->shownWeek());
        $this->travelTo('2026-11-13 12:00'); // the Friday itself
        $this->assertSame(1, $this->shownWeek());
    }

    public function test_it_stays_on_the_week_through_the_sunday_after_it_was_played(): void
    {
        $season = $this->liveLeague();
        $this->play($season, 1);
        $this->night($season, 1)->update(['submitted_at' => now()]);

        $this->travelTo('2026-11-14 10:00'); // Saturday after
        $this->assertSame(1, $this->shownWeek());
        $this->travelTo('2026-11-15 23:30'); // Sunday night
        $this->assertSame(1, $this->shownWeek());
        $this->travelTo('2026-11-16 00:30'); // Monday: week 2's turn
        $this->assertSame(2, $this->shownWeek());
    }

    public function test_before_the_season_it_opens_on_week_one_and_after_it_on_the_last_week(): void
    {
        $this->liveLeague();

        $this->travelTo('2026-11-04 09:00'); // no league night this Monday to Sunday: the next one
        $this->assertSame(1, $this->shownWeek());
        $this->travelTo('2027-06-01 09:00');
        $this->assertSame(19, $this->shownWeek());
    }

    public function test_a_week_off_opens_on_the_next_league_night(): void
    {
        $this->liveLeague();
        $this->night(Season::current(), 2)->update(['date' => '2026-11-27']); // week 2 moved on a week
        $this->travelTo('2026-11-18 09:00'); // the week of the 16th has no league night

        $this->assertSame(2, $this->shownWeek());
    }

    public function test_a_week_can_be_picked_and_a_bad_pick_falls_back(): void
    {
        $this->liveLeague();
        $this->travelTo('2026-11-09 09:00');

        $this->assertSame(5, $this->shownWeek('/results?week=5'));
        $this->assertSame(1, $this->shownWeek('/results?week=99'));
        $this->assertSame(1, $this->shownWeek('/results?week=abc'));
    }

    /* ---- the controls ---- */

    public function test_there_are_arrows_either_side_and_a_dropdown_of_every_week(): void
    {
        $this->liveLeague();
        $html = $this->get('/results?week=3')->getContent();

        $this->assertStringContainsString('href="'.route('results.index', ['week' => 2]).'" data-dir="prev" aria-label="Previous week: week 2"', $html);
        $this->assertStringContainsString('href="'.route('results.index', ['week' => 4]).'" data-dir="next" aria-label="Next week: week 4"', $html);
        $this->assertSame(19, substr_count($html, '<option value='));
        $this->assertMatchesRegularExpression('/<option value="3" selected>Week 3 · 27 Nov<\/option>/', $html);
    }

    public function test_the_arrows_stop_at_the_first_and_last_weeks(): void
    {
        $this->liveLeague();

        $first = $this->get('/results?week=1')->getContent();
        $this->assertStringNotContainsString('aria-label="Previous week', $first);
        $this->assertStringContainsString('aria-label="Next week: week 2"', $first);

        $last = $this->get('/results?week=19')->getContent();
        $this->assertStringContainsString('aria-label="Previous week: week 18"', $last);
        $this->assertStringNotContainsString('aria-label="Next week', $last);
    }

    /* ---- what a week shows ---- */

    public function test_unplayed_fixtures_are_highlighted_in_their_own_week(): void
    {
        $season = $this->liveLeague();
        $this->travelTo('2026-11-09 09:00');

        $html = $this->get('/results?week=2')->getContent();
        $this->assertSame(10, substr_count($html, 'fx-unplayed'), 'every fixture of a week still to come');
        $this->assertSame(10, substr_count($html, '>To play<'));
        $this->assertStringNotContainsString('Checkout:', $html);
    }

    public function test_played_fixtures_show_the_result_and_only_the_rest_are_highlighted(): void
    {
        $season = $this->liveLeague();
        $this->travelTo('2026-11-09 09:00');
        $played = $this->play($season, 1, 121);

        $html = $this->get('/results?week=1')->getContent();
        $this->assertSame(9, substr_count($html, 'fx-unplayed'));
        $this->assertStringContainsString('Checkout:', $html);
        $this->assertStringContainsString('121', $html);
    }

    public function test_fixtures_left_unplayed_after_a_week_closes_say_postponed(): void
    {
        $season = $this->liveLeague();
        $this->travelTo('2026-11-14 09:00');
        $this->play($season, 1);
        $this->night($season, 1)->update(['submitted_at' => now()]);

        $html = $this->get('/results?week=1')->getContent();
        $this->assertSame(9, substr_count($html, '>Postponed<'));
        $this->assertStringNotContainsString('>To play<', $html);
    }

    public function test_a_week_that_has_come_and_gone_with_nothing_entered_says_a_result_is_to_come(): void
    {
        $this->liveLeague();
        $this->travelTo('2026-11-14 09:00');

        $this->assertSame(10, substr_count($this->get('/results?week=1')->getContent(), '>Result to come<'));
    }

    public function test_a_catch_up_played_later_shows_as_a_result_in_its_own_week(): void
    {
        $season = $this->liveLeague();
        $week1 = $this->night($season, 1);
        $this->submitLines($week1, '');
        [$f, $a, $b] = $this->firstMatch($week1);
        $this->submitLines($this->night($season, 2), "{$a} beat {$b} 77");

        $one = $this->get('/results?week=1')->getContent();
        $this->assertStringContainsString('77', $one);
        $this->assertSame(9, substr_count($one, '>Postponed<'), 'the other nine are still to play');
        $this->assertStringNotContainsString('77', $this->get('/results?week=2')->getContent());
    }

    public function test_a_bye_is_listed_for_the_week(): void
    {
        $this->liveLeague(5);

        $this->get('/results?week=1')->assertSee('Bye:');
    }

    public function test_edit_buttons_are_for_admins_only(): void
    {
        $season = $this->liveLeague();
        $fixture = $this->play($season, 3);

        $this->get('/results?week=3')->assertDontSee('>Edit<', false);
        $this->actingAs($this->admin());
        $this->get('/results?week=3')->assertSee('>Edit<', false);
    }

    public function test_the_not_entered_yet_note_links_to_that_week_from_another(): void
    {
        $season = $this->liveLeague();
        $this->travelTo('2026-11-13 22:30'); // week 1's night, after ten, nothing entered

        $this->get('/results?week=1')->assertSee('Week 1 results (Friday 13 November) haven’t been entered yet.')->assertDontSee('View week 1');
        $this->get('/results?week=2')->assertSee('haven’t been entered yet')->assertSee('View week 1');

        $this->actingAs($this->admin());
        $this->get('/results?week=1')->assertSee(route('admin.results.index'));

        [$f, $a, $b] = $this->firstMatch($this->night($season, 1));
        $this->submitLines($this->night($season, 1), "{$a} beat {$b} 121");
        $this->get('/results?week=1')->assertDontSee('haven’t been entered yet');
    }

    public function test_the_week_controls_swap_in_place_without_a_page_reload(): void
    {
        $this->liveLeague();
        $html = $this->get('/results?week=3')->getContent();

        $this->assertStringContainsString('id="week-panel"', $html);
        $this->assertStringContainsString('js/weeks.js', $html);
        $this->assertStringNotContainsString('onchange=', $html, 'the dropdown is handled by the script, which keeps the pointer where it is');
        $this->assertStringContainsString('<noscript><button', $html, 'and still works with no JavaScript');

        // The script swaps only the panel, and falls back to a normal load if anything goes wrong
        $js = file_get_contents(public_path('js/weeks.js'));
        $this->assertStringContainsString("getElementById('week-panel')", $js);
        $this->assertStringContainsString('window.location.href = url', $js);
        $this->assertStringContainsString('history.pushState', $js);
    }

    public function test_the_panel_holds_everything_that_changes_with_the_week(): void
    {
        $season = $this->liveLeague();
        $html = $this->get('/results?week=2')->getContent();

        $panel = substr($html, strpos($html, 'id="week-panel"'));
        foreach (['<select', 'Week 2</h2>', 'data-dir="prev"', 'data-dir="next"', 'fx-unplayed'] as $part) {
            $this->assertStringContainsString($part, $panel);
        }
        $this->assertLessThan(strpos($html, 'id="week-panel"'), strpos($html, 'Results and fixtures</h1>'), 'the heading stays outside it');
    }

    /* ---- add a result straight from an unplayed fixture ---- */

    public function test_admins_get_an_add_result_link_on_unplayed_fixtures_only(): void
    {
        $season = $this->liveLeague();
        $this->play($season, 3);

        $this->get('/results?week=3')->assertDontSee('Add result')->assertDontSee('/admin/results/fixture', false);

        $this->actingAs($this->admin());
        $html = $this->get('/results?week=3')->getContent();
        $this->assertSame(9, substr_count($html, '>Add result</a>'), 'nine of the ten are unplayed');
        $this->assertSame(1, substr_count($html, '>Edit</a>'), 'the played one has Edit');
        $this->assertStringContainsString('/admin/results/fixture/', $html);
    }

    public function test_adding_a_result_to_a_fixture(): void
    {
        $season = $this->liveLeague();
        $this->actingAs($this->admin());
        $fixture = Fixture::where('season_id', $season->id)->where('league_night_id', $this->night($season, 2)->id)->orderBy('id')->first();
        $names = [$fixture->player1_id => Player::find($fixture->player1_id)->name, $fixture->player2_id => Player::find($fixture->player2_id)->name];
        $back = '/results?week=2';

        $this->get(route('admin.results.create', [$fixture, 'back' => $back]))->assertOk()
            ->assertSee(array_values($names)[0].' v '.array_values($names)[1])->assertSee('Choose the winner')->assertSee('Week 2');

        $this->post(route('admin.results.store', $fixture), ['winner_id' => $fixture->player2_id, 'checkout' => 96, 'back' => $back])
            ->assertRedirect($back)
            ->assertSessionHas('status', fn ($m) => str_contains($m, 'Result added: '.$names[$fixture->player2_id].' beat '.$names[$fixture->player1_id]));

        $result = $fixture->fresh()->result;
        $this->assertSame([$fixture->player2_id, 96], [$result->winner_id, $result->checkout]);
        $this->assertSame($fixture->league_night_id, $result->submitted_night_id);

        // The week doesn't close just because one result was added
        $this->assertNull($this->night($season, 2)->fresh()->submitted_at);
        $this->get($back)->assertSee('Checkout:')->assertSee('96');
    }

    public function test_adding_a_result_is_validated(): void
    {
        $season = $this->liveLeague();
        $this->actingAs($this->admin());
        $fixture = Fixture::where('season_id', $season->id)->orderBy('id')->first();

        $this->post(route('admin.results.store', $fixture), ['winner_id' => '', 'checkout' => 50])->assertSessionHasErrors('winner_id');
        $this->post(route('admin.results.store', $fixture), ['winner_id' => 99999, 'checkout' => 50])->assertSessionHasErrors('winner_id');
        $this->post(route('admin.results.store', $fixture), ['winner_id' => $fixture->player1_id, 'checkout' => 171])->assertSessionHasErrors('checkout');
        $this->post(route('admin.results.store', $fixture), ['winner_id' => $fixture->player1_id, 'checkout' => 1])->assertSessionHasErrors('checkout');
        $this->post(route('admin.results.store', $fixture), ['winner_id' => $fixture->player1_id, 'checkout' => 'x'])->assertSessionHasErrors('checkout');
        $this->assertNull($fixture->fresh()->result);
    }

    public function test_a_match_that_already_has_a_result_goes_to_edit_instead(): void
    {
        $season = $this->liveLeague();
        $this->actingAs($this->admin());
        $fixture = $this->play($season, 1, 60);

        $this->get(route('admin.results.create', [$fixture, 'back' => '/results?week=1']))->assertRedirect();
        $this->post(route('admin.results.store', $fixture), ['winner_id' => $fixture->player2_id, 'checkout' => 80])
            ->assertRedirect()->assertSessionHasErrors('result');
        $this->assertSame(60, $fixture->fresh()->result->checkout, 'nothing was overwritten');
        $this->assertSame(1, Result::count());
    }

    public function test_the_add_result_screen_needs_a_login_and_cannot_send_you_elsewhere(): void
    {
        $season = $this->liveLeague();
        $fixture = Fixture::where('season_id', $season->id)->orderBy('id')->first();

        $this->get(route('admin.results.create', $fixture))->assertRedirect('/admin/login');
        $this->post(route('admin.results.store', $fixture), ['winner_id' => $fixture->player1_id, 'checkout' => 50])->assertRedirect('/admin/login');

        $this->actingAs($this->admin());
        $this->post(route('admin.results.store', $fixture), ['winner_id' => $fixture->player1_id, 'checkout' => 50, 'back' => '//evil.example'])
            ->assertRedirect(route('results.index', [], false));
    }

    public function test_adding_a_result_to_a_night_marked_no_results_clears_that_mark(): void
    {
        $season = $this->liveLeague();
        $night = $this->night($season, 1);
        $night->update(['submitted_at' => now(), 'no_results' => true]);
        $fixture = Fixture::where('league_night_id', $night->id)->orderBy('id')->first();
        $this->actingAs($this->admin());

        $this->post(route('admin.results.store', $fixture), ['winner_id' => $fixture->player1_id, 'checkout' => 40]);

        $this->assertFalse($night->fresh()->no_results);
    }

    /* ---- the dropdown's arrow ---- */

    public function test_dropdowns_draw_their_own_arrow_with_room_from_the_edge(): void
    {
        $css = file_get_contents(public_path('css/app.css'));

        preg_match('/select\.field \{([^}]*)\}/', $css, $m);
        foreach (['appearance: none', 'background-image: var(--chevron)', 'padding-right: 44px', 'background-position: right 16px center'] as $rule) {
            $this->assertStringContainsString($rule, $m[1] ?? '', $rule);
        }
        $this->assertSame(2, substr_count($css, '--chevron: url('), 'one for the dark pages and one for the light ones');
    }
}
