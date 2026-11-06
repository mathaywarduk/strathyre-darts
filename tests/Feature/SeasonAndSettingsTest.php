<?php

namespace Tests\Feature;

use App\Models\Expense;
use App\Models\Fixture;
use App\Models\Player;
use App\Models\Result;
use App\Models\Season;
use App\Models\Setting;
use App\Models\SubsPayment;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\BuildsLeague;
use Tests\TestCase;

class SeasonAndSettingsTest extends TestCase
{
    use BuildsLeague, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->actingAs($this->admin());
    }

    private function playSomeSeason(): Season
    {
        $season = $this->liveLeague();
        $this->travelTo('2026-11-14 10:00');
        [, $a, $b] = $this->firstMatch($this->night($season, 1));
        $this->submitLines($this->night($season, 1), "{$a} beat {$b} 121");
        $this->post(route('admin.subs.store'), ['player_id' => Player::first()->id, 'amount' => '2', 'paid_on' => '2026-11-14']);
        $this->post(route('admin.expenses.store'), ['item' => 'Chalk', 'cost' => '£3.50', 'spent_on' => '2026-11-10']);

        return $season;
    }

    public function test_settings_must_be_set_and_are_validated(): void
    {
        $this->put(route('admin.settings.update'), ['win_points' => 3, 'loss_points' => 1, 'subs' => '2.50'])->assertRedirect();
        $s = Setting::current();
        $this->assertSame([3, 1, 250], [$s->win_points, $s->loss_points, $s->subs_pence]);

        $this->put(route('admin.settings.update'), ['win_points' => 3, 'loss_points' => 1, 'subs' => 'lots'])->assertSessionHasErrors('subs');
        $this->put(route('admin.settings.update'), ['win_points' => 'many', 'loss_points' => 1, 'subs' => '2'])->assertSessionHasErrors('win_points');
    }

    public function test_the_league_rules_can_be_cleared_to_hand_over_empty(): void
    {
        $this->liveLeague();
        $this->get('/')->assertDontSee('Coming soon');

        $this->put(route('admin.settings.update'), ['win_points' => '', 'loss_points' => '', 'subs' => ''])
            ->assertRedirect()->assertSessionHasNoErrors()
            ->assertSessionHas('status', fn ($m) => str_contains($m, 'Coming soon'));

        $s = Setting::current();
        $this->assertSame([null, null, null], [$s->win_points, $s->loss_points, $s->subs_pence]);
        $this->assertSame(['points for a win', 'points for a loss', 'subs per league night'], $s->missing());
        auth()->logout();
        $this->get('/')->assertSee('Coming soon');
    }

    public function test_one_rule_can_be_cleared_on_its_own(): void
    {
        $this->put(route('admin.settings.update'), ['win_points' => 3, 'loss_points' => '', 'subs' => '2.50'])->assertSessionHasNoErrors();
        $s = Setting::current();
        $this->assertSame([3, null, 250], [$s->win_points, $s->loss_points, $s->subs_pence]);
        $this->get(route('admin.settings.edit'))->assertSee('Still to set: points for a loss');
    }

    public function test_the_admin_can_change_their_password(): void
    {
        $this->put(route('admin.settings.password'), ['current_password' => 'nope', 'password' => 'a-long-new-password', 'password_confirmation' => 'a-long-new-password'])
            ->assertSessionHasErrors('current_password');
        $this->put(route('admin.settings.password'), ['current_password' => 'password', 'password' => 'short', 'password_confirmation' => 'short'])
            ->assertSessionHasErrors('password');
        $this->put(route('admin.settings.password'), ['current_password' => 'password', 'password' => 'a-long-new-password', 'password_confirmation' => 'a-long-new-password'])
            ->assertSessionHasNoErrors();

        auth()->logout();
        $this->post('/admin/login', ['username' => 'admin', 'password' => 'a-long-new-password'])->assertRedirect('/admin');
    }

    public function test_expenses_can_be_added_edited_and_deleted(): void
    {
        $season = $this->liveLeague();
        $this->post(route('admin.expenses.store'), ['item' => 'Dartboard', 'cost' => '45', 'spent_on' => '2026-11-03'])->assertRedirect();
        $expense = Expense::first();
        $this->assertSame(4500, $expense->cost_pence);

        $this->put(route('admin.expenses.update', $expense), ['item' => 'New dartboard', 'cost' => '47.50', 'spent_on' => '2026-11-04']);
        $this->assertSame([4750, 'New dartboard'], [$expense->fresh()->cost_pence, $expense->fresh()->item]);
        $this->post(route('admin.expenses.store'), ['item' => 'x', 'cost' => '-5', 'spent_on' => '2026-11-04'])->assertSessionHasErrors('cost');

        $this->delete(route('admin.expenses.destroy', $expense));
        $this->assertSame(0, Expense::count());
    }

    public function test_resetting_archives_the_season_publicly_and_starts_a_fresh_one(): void
    {
        $season = $this->playSomeSeason();

        $this->post(route('admin.season.reset'), ['starts_on' => '2027-11-12', 'confirm' => 'nope'])->assertSessionHasErrors('confirm');
        $this->post(route('admin.season.reset'), ['starts_on' => '2027-11-13', 'confirm' => 'RESET'])->assertSessionHasErrors('starts_on'); // a Saturday
        $this->assertTrue(Season::current()->is($season));

        $this->post(route('admin.season.reset'), ['starts_on' => '2027-11-12', 'confirm' => 'RESET'])->assertRedirect(route('admin.fixtures.index'));

        $old = $season->fresh();
        $this->assertTrue($old->isArchived());
        $this->assertSame('2026/27', $old->label);
        $this->assertSame(200 - 350, $old->closing_pence);

        $new = Season::current();
        $this->assertNotTrue($new->is($old));
        $this->assertSame(-150, $new->opening_pence, 'the balance carries forward');
        $this->assertSame(0, $new->fixtures()->count());
        $this->assertSame(0, $new->payments()->count());
        $this->assertSame(0, $new->expenses()->count());

        // The archived season stays visible, with names as they were, even if a player is renamed later
        Player::first()->update(['name' => 'Renamed R']);
        auth()->logout();
        $this->get('/seasons')->assertSee('Season 2026/27');
        $this->get(route('seasons.show', $old))->assertOk()->assertSee('Final table')->assertSeeInOrder(['Checkout:', '121'])->assertSee('Highest checkout')->assertDontSee('Renamed R');
        $this->get('/')->assertSee('Coming soon')->assertSee('Past seasons');
    }

    public function test_finance_carries_the_opening_balance(): void
    {
        $this->playSomeSeason();
        $this->post(route('admin.season.reset'), ['starts_on' => '2027-11-12', 'confirm' => 'RESET']);
        $this->post(route('admin.fixtures.generate'), ['start_on' => '2027-11-12']);
        $this->post(route('admin.fixtures.save'), ['token' => session('fixture_draft.token')]);

        $this->travelTo('2027-11-01');
        $this->get('/finance')->assertSee('-£1.50')->assertSee('carried over');
    }

    public function test_a_season_can_be_hard_deleted_with_everything_in_it(): void
    {
        $season = $this->playSomeSeason();
        $this->assertGreaterThan(0, Fixture::count());

        $this->delete(route('admin.season.destroy', $season), ['confirm' => 'delete'])->assertSessionHasErrors('confirm');
        $this->assertNotNull(Season::find($season->id));

        $this->delete(route('admin.season.destroy', $season), ['confirm' => 'DELETE'])->assertRedirect(route('admin.season.index'));

        $this->assertNull(Season::find($season->id));
        foreach ([Fixture::class, Result::class, SubsPayment::class, Expense::class] as $model) {
            $this->assertSame(0, $model::count(), $model.' should be empty');
        }
        $this->assertSame(20, Player::count(), 'players are kept');
        $this->get('/admin/season')->assertOk()->assertSee('Start a season');
    }

    public function test_an_archived_season_can_be_hard_deleted_too(): void
    {
        $season = $this->playSomeSeason();
        $this->post(route('admin.season.reset'), ['starts_on' => '2027-11-12', 'confirm' => 'RESET']);

        $this->delete(route('admin.season.destroy', $season), ['confirm' => 'DELETE'])->assertRedirect();
        $this->assertNull(Season::find($season->id));
        $this->assertNotNull(Season::current());
        auth()->logout();
        $this->get(route('seasons.show', $season->id))->assertNotFound();
    }

    public function test_dashboard_lists_what_is_left_to_set_up(): void
    {
        $this->get('/admin')->assertOk()->assertSee('Get the league ready')->assertSee('Still to set');
    }

    public function test_admin_pages_render(): void
    {
        $this->liveLeague();
        $this->travelTo('2026-11-14 10:00');

        foreach (['admin.dashboard', 'admin.players.index', 'admin.fixtures.index', 'admin.results.index', 'admin.expenses.index', 'admin.subs', 'admin.settings.edit', 'admin.season.index'] as $route) {
            $this->get(route($route))->assertOk();
        }
        $this->get(route('admin.players.edit', Player::first()))->assertOk();
    }
}
