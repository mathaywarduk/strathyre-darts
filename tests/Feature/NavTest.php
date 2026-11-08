<?php

namespace Tests\Feature;

use App\Models\Player;
use App\Models\Season;
use App\Models\Setting;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\BuildsLeague;
use Tests\TestCase;

/** Links only appear once the page they lead to can work. */
class NavTest extends TestCase
{
    use BuildsLeague, RefreshDatabase;

    public function test_before_anything_is_set_up_guests_see_no_nav_at_all(): void
    {
        $this->get('/')->assertOk()->assertSee('Coming soon')->assertDontSee('aria-label="Main"', false)->assertDontSee('Finance');
    }

    public function test_the_finance_link_appears_once_the_league_is_live(): void
    {
        $this->get('/')->assertDontSee('/finance', false);

        $this->liveLeague();
        $this->get('/')->assertSee('aria-label="Main"', false)->assertSee(route('finance.index'));

        Setting::current()->update(['subs_pence' => null]); // a cleared rule takes it away again
        $this->get('/')->assertDontSee(route('finance.index'));
    }

    public function test_the_admin_sees_an_admin_link_but_no_finance_link_until_live(): void
    {
        $this->actingAs($this->admin());
        $this->get('/')->assertSee(route('admin.dashboard'))->assertDontSee(route('finance.index'));
    }

    public function test_admin_links_follow_how_far_set_up_the_league_is(): void
    {
        $this->actingAs($this->admin());
        $nav = fn ($label) => ">{$label}</a>";

        // No season yet: only the always-available links (the setup list on the page still points the way)
        $page = $this->get(route('admin.dashboard'));
        foreach (['Fixtures', 'Expenses', 'Results', 'Subs'] as $label) {
            $page->assertDontSee($nav($label), false);
        }
        foreach (['Dashboard', 'Players', 'Settings', 'Season'] as $label) {
            $page->assertSee($nav($label), false);
        }
        $page->assertSee('Quick links')->assertSee('Start a doubles tournament')->assertDontSee('Enter or check results')->assertDontSee('Add an expense');

        // A season, but no fixtures or rules
        Season::create(['starts_on' => '2026-11-13', 'status' => 'current']);
        $page = $this->get(route('admin.dashboard'));
        $page->assertSee($nav('Fixtures'), false)->assertSee($nav('Expenses'), false)
            ->assertDontSee($nav('Results'), false)->assertDontSee($nav('Subs'), false)
            ->assertSee('Add an expense')->assertDontSee('Enter or check results');
    }

    public function test_every_admin_link_shows_once_the_league_is_live(): void
    {
        $this->liveLeague();
        $this->actingAs($this->admin());

        $page = $this->get(route('admin.dashboard'));
        foreach (['Results', 'Subs', 'Fixtures', 'Expenses'] as $label) {
            $page->assertSee(">{$label}</a>", false);
        }
        $page->assertSee('Enter or check results')->assertSee('href="'.route('finance.index').'"', false);
    }

    public function test_typed_in_addresses_redirect_instead_of_showing_a_404(): void
    {
        $early = Player::create(['name' => 'Early E', 'ranking' => 3, 'gender' => 'M']);
        $this->get('/finance')->assertRedirect(route('home'));
        $this->get('/finance/weeks/1')->assertRedirect(route('home'));
        $this->get(route('players.show', $early))->assertRedirect(route('home'));

        $this->liveLeague();
        $this->travelTo('2026-11-14 10:00');
        $this->get('/finance/weeks/2')->assertRedirect(route('finance.index'));
        $this->get('/finance/weeks/99')->assertRedirect(route('finance.index'));
        $this->get('/players/9999')->assertNotFound(); // no such player at all
        $stranger = Player::create(['name' => 'Stranger S', 'ranking' => 3, 'gender' => 'M']);
        $this->get(route('players.show', $stranger))->assertRedirect(route('home'));
        $this->get('/seasons/'.Season::first()->id)->assertRedirect(route('seasons.index'));
    }

    public function test_admin_pages_show_the_next_setup_step_until_the_league_is_live(): void
    {
        $this->actingAs($this->admin());

        $this->get(route('admin.players.index'))->assertSee('Setup 0 of 4')->assertSee('Next:')->assertSee('Upload the players');
        $this->get(route('admin.dashboard'))->assertDontSee('Setup 0 of 4'); // the checklist is on that page

        $this->makePlayers(4);
        $this->get(route('admin.settings.edit'))->assertSee('Setup 1 of 4')->assertSee('Set points and subs');

        Setting::current()->update(['win_points' => 2, 'loss_points' => 1, 'subs_pence' => 200]);
        $this->get(route('admin.players.index'))->assertSee('Setup 2 of 4')->assertSee('Start a season');

        $season = Season::create(['starts_on' => '2026-11-13', 'status' => 'current']);
        $this->get(route('admin.settings.edit'))->assertSee('Setup 3 of 4')->assertSee('Save the fixtures');

        $this->post(route('admin.fixtures.generate'), ['start_on' => '2026-11-13']);
        $this->post(route('admin.fixtures.save'), ['token' => session('fixture_draft.token')]);
        $this->get(route('admin.settings.edit'))->assertDontSee('Setup 3 of 4')->assertDontSee('Setup 4 of 4')->assertDontSee('Next:');
        $this->assertTrue($season->fixtures()->exists());
    }

    public function test_the_setup_bar_never_shows_to_guests(): void
    {
        $this->get(route('admin.login'))->assertOk()->assertDontSee('Setup 0 of 4');
    }
}
