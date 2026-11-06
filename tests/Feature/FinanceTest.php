<?php

namespace Tests\Feature;

use App\Models\Season;
use App\Models\Setting;
use App\Models\SubsPayment;
use App\Services\Finance;
use App\Services\League;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\BuildsLeague;
use Tests\TestCase;

class FinanceTest extends TestCase
{
    use BuildsLeague, RefreshDatabase;

    private Season $season;

    protected function setUp(): void
    {
        parent::setUp();
        $this->season = $this->liveLeague();
        $this->actingAs($this->admin());
    }

    private function finance(): Finance
    {
        return new Finance(new League($this->season, Setting::current()));
    }

    public function test_subs_are_due_from_each_nights_date_for_every_player(): void
    {
        $this->travelTo('2026-11-12 12:00');
        $this->assertSame(0, $this->finance()->owed());

        $this->travelTo('2026-11-13 18:00'); // week 1's night
        $this->assertSame(20 * 200, $this->finance()->owed());

        $this->travelTo('2026-11-21 09:00'); // weeks 1 and 2 are due
        $this->assertSame(20 * 2 * 200, $this->finance()->owed());
    }

    public function test_subs_are_due_even_when_a_match_is_postponed(): void
    {
        $this->travelTo('2026-11-14 09:00');
        $this->submitLines($this->night($this->season, 1), '');

        $this->assertSame(20 * 200, $this->finance()->owed());
    }

    public function test_toggling_a_payment_updates_the_week_and_the_totals(): void
    {
        $this->travelTo('2026-11-21 09:00');
        $player = $this->finance()->league->players->first();

        $this->post(route('finance.toggle', [1, $player]))->assertRedirect(route('finance.week', 1).'#p'.$player->id);
        $this->assertSame(1, SubsPayment::count());

        $f = $this->finance();
        $this->assertSame(200, $f->received());
        $this->assertSame([2], $f->weeksOwed($player->id));
        $this->assertSame(20 * 2 * 200 - 200, $f->owed());
        $this->assertSame(200, $f->balance());

        $this->get(route('finance.week', 1))->assertOk()->assertSee('Not paid (19)')->assertSee('Paid (1)');

        $this->post(route('finance.toggle', [1, $player]));
        $this->assertSame(0, SubsPayment::count());
    }

    public function test_subs_can_be_paid_in_advance_for_a_week_that_is_not_due_yet(): void
    {
        $this->travelTo('2026-11-14 09:00'); // week 1 is due, week 2 isn't
        $player = $this->finance()->league->players->first();

        $this->get(route('finance.week', 2))->assertOk()->assertSee('Not due yet');
        $this->post(route('finance.toggle', [2, $player]));

        $f = $this->finance();
        $this->assertSame(200, $f->received(), 'counted as received');
        $this->assertSame(20 * 200, $f->owed(), 'but only week 1 is owed so far');
        $this->assertSame(0, $f->week($this->night($this->season, 2))['owed']);
        $this->get('/finance')->assertSee('1 paid in advance')->assertSee('Due Friday 20 November');
        $this->get(route('finance.week', 99))->assertNotFound();
    }

    public function test_finance_page_shows_balance_owing_income_and_expenses(): void
    {
        $this->travelTo('2026-11-21 09:00');
        $player = $this->finance()->league->players->first();
        $this->post(route('finance.toggle', [1, $player]));
        $this->post(route('admin.expenses.store'), ['item' => 'New dartboard', 'cost' => '45', 'spent_on' => '2026-11-10'])->assertRedirect();

        $this->get('/finance')->assertOk()
            ->assertSee('-£43.00') // £2.00 received less £45.00 spent
            ->assertSee('New dartboard')
            ->assertSee('£45.00')
            ->assertSee('1 of 20 paid');
    }

    public function test_owing_lists_weeks_and_amounts_most_first(): void
    {
        $this->travelTo('2026-11-21 09:00');
        $players = $this->finance()->league->players->values();
        $this->post(route('finance.toggle', [1, $players[0]]));
        $this->post(route('finance.toggle', [1, $players[1]]));
        $this->post(route('finance.toggle', [2, $players[1]]));

        $owing = $this->finance()->owing();
        $this->assertCount(19, $owing, 'the player who paid both weeks is gone');
        $this->assertSame(2, count($owing->first()['weeks']));
        $this->assertSame(200, $owing->last()['amount']);
        $this->assertSame('weeks 1, 2', Finance::weeksText([1, 2]));
        $this->assertSame('Week 2', Finance::weeksText([2], true));
    }

    public function test_player_page_shows_subs_to_the_admin_only(): void
    {
        $this->travelTo('2026-11-21 09:00');
        $player = $this->finance()->league->players->first();
        $this->post(route('finance.toggle', [1, $player]));

        $this->get(route('players.show', $player))->assertSee('£2.00 paid of £4.00 due so far')->assertSee('Owes £2.00 (week 2)');
        auth()->logout();
        $this->get(route('players.show', $player))->assertDontSee('due so far');
    }

    public function test_subs_owed_report(): void
    {
        $this->travelTo('2026-11-14 09:00');
        $this->get(route('admin.subs'))->assertOk()->assertSee('£40.00')->assertSee('Week 1');
    }

    public function test_finance_explains_when_the_league_is_not_set_up_yet(): void
    {
        Season::query()->delete();
        Setting::current()->update(['win_points' => null]);
        Season::create(['starts_on' => '2026-11-13', 'status' => 'current']);

        $this->get('/finance')->assertRedirect(route('admin.dashboard'))->assertSessionHasErrors('finance');
        $this->get('/finance/weeks/1')->assertRedirect(route('admin.dashboard'));
        auth()->logout();
        $this->get('/finance')->assertNotFound();
    }
}
