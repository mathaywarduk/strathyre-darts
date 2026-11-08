<?php

namespace Tests\Feature;

use App\Models\Fixture;
use App\Models\Player;
use App\Models\Result;
use App\Models\Season;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\BuildsLeague;
use Tests\TestCase;

class ResultsFlowTest extends TestCase
{
    use BuildsLeague, RefreshDatabase;

    private Season $season;

    protected function setUp(): void
    {
        parent::setUp();
        $this->season = $this->liveLeague();
        $this->actingAs($this->admin());
    }

    private function preview(int $week, string $lines, string $mode = 'results')
    {
        return $this->post(route('admin.results.preview'), ['week' => $this->night($this->season, $week)->id, 'mode' => $mode, 'lines' => $lines]);
    }

    public function test_preview_lists_results_postponed_matches_and_unmatched_lines(): void
    {
        $night = $this->night($this->season, 1);
        [, $a, $b] = $this->firstMatch($night);

        $this->preview(1, "{$a} beat {$b} 15\nNobody Here beat {$b} 20\n{$a} beat {$b} 0\nnot a result")
            ->assertOk()
            ->assertSee('Results (1)')
            ->assertSee('Postponed (9)')
            ->assertSee('Lines that need fixing (3)')
            ->assertSee('No player called')
            ->assertSee('The checkout must be a whole number from 2 to 170.')
            ->assertSee('Couldn’t read this line')
            ->assertDontSee('Confirm and save'); // can't save until the lines are fixed
    }

    public function test_a_checkout_must_be_a_whole_number_from_2_to_170(): void
    {
        [, $a, $b] = $this->firstMatch($this->night($this->season, 1));

        $this->preview(1, "{$a} beat {$b} 170")->assertSee('Confirm and save');
        $this->preview(1, "{$a} beat {$b} 2")->assertSee('Confirm and save');
        $this->preview(1, "{$a} beat {$b} 171")->assertSee('from 2 to 170');
        $this->preview(1, "{$a} beat {$b} 1")->assertSee('from 2 to 170');
        $this->preview(1, "{$a} beat {$b} 12.5")->assertSee('Couldn’t read this line');
    }

    public function test_a_pair_with_no_fixture_is_unmatched(): void
    {
        $a = Player::find(1);
        $played = Fixture::where('player1_id', 1)->orWhere('player2_id', 1)->pluck('player1_id', 'player2_id');
        // Everyone meets everyone, so make a stranger instead
        $stranger = Player::create(['name' => 'Stranger S', 'ranking' => 3, 'gender' => 'M']);

        $this->preview(1, "{$a->name} beat {$stranger->name} 40")->assertSee('No player called')->assertSee('Stranger S');
    }

    public function test_saving_records_results_and_turns_the_rest_into_catch_ups(): void
    {
        $night = $this->night($this->season, 1);
        [$f, $a, $b] = $this->firstMatch($night);

        $this->post(route('admin.results.save'), ['week' => $night->id, 'mode' => 'results', 'lines' => strtoupper("{$a} beat {$b} 15")])
            ->assertRedirect(route('admin.dashboard'))
            ->assertSessionHas('status_link');

        $this->assertSame(1, Result::count());
        $this->assertSame(15, $f->fresh()->result->checkout);
        $this->assertNotNull($night->fresh()->submitted_at);
        $this->get('/')->assertSee('9 catch-ups to play');
    }

    public function test_lines_with_problems_are_refused_on_save(): void
    {
        $night = $this->night($this->season, 1);
        $this->post(route('admin.results.save'), ['week' => $night->id, 'mode' => 'results', 'lines' => 'rubbish'])->assertStatus(422);
        $this->assertSame(0, Result::count());
        $this->assertNull($night->fresh()->submitted_at);
    }

    public function test_the_same_fixture_twice_in_one_list_is_flagged(): void
    {
        [, $a, $b] = $this->firstMatch($this->night($this->season, 1));

        $this->preview(1, "{$a} beat {$b} 15\n{$b} beat {$a} 20")->assertSee('is already in this list');
    }

    public function test_an_existing_result_is_flagged_as_replaced(): void
    {
        $night = $this->night($this->season, 1);
        [$f, $a, $b] = $this->firstMatch($night);
        $this->submitLines($night, "{$a} beat {$b} 15");

        $this->preview(2, "{$b} beat {$a} 60")->assertSee('Replaces the existing result')->assertSee('Catch-up from week 1');
    }

    public function test_a_match_played_early_is_accepted_and_labelled(): void
    {
        $week7 = $this->night($this->season, 7);
        [$f, $a, $b] = $this->firstMatch($week7);

        $this->preview(5, "{$a} beat {$b} 44")->assertSee('Played early: week 7 fixture');
        $this->submitLines($this->night($this->season, 5), "{$a} beat {$b} 44");

        $this->assertSame(44, $f->fresh()->result->checkout);
        $this->assertNull($week7->fresh()->submitted_at, 'week 7 is still to come');
    }

    public function test_no_results_this_week_makes_every_match_a_catch_up_and_moves_on(): void
    {
        $night = $this->night($this->season, 1);

        $this->preview(1, '', 'none')->assertOk()->assertSee('All 10 matches become catch-ups');
        $this->assertNull($night->fresh()->submitted_at, 'nothing saved by the preview');

        $this->post(route('admin.results.save'), ['week' => $night->id, 'mode' => 'none'])->assertRedirect(route('admin.dashboard'));

        $this->assertTrue($night->fresh()->no_results);
        $this->get('/')->assertSee('Week 2')->assertSee('10 catch-ups to play');
        $this->get('/results?week=1')->assertSee('Postponed');
    }

    public function test_editing_a_result_changes_the_winner_and_the_points_left(): void
    {
        $night = $this->night($this->season, 1);
        [$f, $a, $b] = $this->firstMatch($night);
        $this->submitLines($night, "{$a} beat {$b} 15");
        $result = $f->fresh()->result;

        $this->get(route('admin.results.edit', [$result, 'back' => '/']))->assertOk()->assertSee("{$a} v {$b}");
        $this->put(route('admin.results.update', $result), ['winner_id' => $f->player2_id, 'checkout' => 80, 'back' => '/'])->assertRedirect('/');

        $result->refresh();
        $this->assertSame($f->player2_id, $result->winner_id);
        $this->assertSame(80, $result->checkout);

        $this->put(route('admin.results.update', $result), ['winner_id' => $f->player2_id, 'checkout' => 171])->assertSessionHasErrors('checkout');
        $this->put(route('admin.results.update', $result), ['winner_id' => 9999, 'checkout' => 5])->assertSessionHasErrors('winner_id');
    }

    public function test_clearing_a_result_sends_the_match_back_to_catch_ups(): void
    {
        $night = $this->night($this->season, 1);
        [$f, $a, $b] = $this->firstMatch($night);
        $this->submitLines($night, "{$a} beat {$b} 15");

        $this->delete(route('admin.results.destroy', $f->fresh()->result), ['back' => '/'])->assertRedirect('/');

        $this->assertNull($f->fresh()->result);
        $this->get('/')->assertSee('10 catch-ups to play');
    }

    public function test_the_back_link_cannot_leave_the_site(): void
    {
        $night = $this->night($this->season, 1);
        [$f, $a, $b] = $this->firstMatch($night);
        $this->submitLines($night, "{$a} beat {$b} 15");

        $this->put(route('admin.results.update', $f->fresh()->result), ['winner_id' => $f->player1_id, 'checkout' => 9, 'back' => '//evil.example'])
            ->assertRedirect(route('home', [], false));
    }

    public function test_the_week_dropdown_does_not_say_this_week(): void
    {
        $this->get(route('admin.results.index'))->assertOk()->assertSee('Week 1')->assertDontSee('· this week');
        $this->submitLines($this->night($this->season, 1), '');
        $this->get(route('admin.results.index'))->assertSee('results in');
    }
}
