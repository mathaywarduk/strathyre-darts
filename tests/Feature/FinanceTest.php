<?php

namespace Tests\Feature;

use App\Models\Player;
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
        $this->season = $this->liveLeague(); // £2.00 a night
        $this->actingAs($this->admin());
    }

    private function finance(): Finance
    {
        return new Finance(new League($this->season, Setting::current()));
    }

    private function pay(Player $player, string $amount, string $date = '2026-11-14')
    {
        return $this->post(route('admin.subs.store'), ['player_id' => $player->id, 'amount' => $amount, 'paid_on' => $date]);
    }

    private function player(string $name = 'Alice A'): Player
    {
        return Player::where('name', $name)->firstOrFail();
    }

    public function test_subs_are_due_from_each_nights_date_for_every_player(): void
    {
        $this->travelTo('2026-11-12 12:00');
        $this->assertSame(0, $this->finance()->owed());

        $this->travelTo('2026-11-13 18:00'); // week 1's night
        $this->assertSame(20 * 200, $this->finance()->owed());

        $this->travelTo('2026-11-21 09:00'); // weeks 1 and 2
        $this->assertSame(20 * 2 * 200, $this->finance()->owed());
    }

    public function test_subs_are_due_even_when_a_match_is_postponed(): void
    {
        $this->travelTo('2026-11-14 09:00');
        $this->submitLines($this->night($this->season, 1), '');

        $this->assertSame(20 * 200, $this->finance()->owed());
    }

    public function test_a_payment_of_any_amount_reduces_what_is_owed(): void
    {
        $this->travelTo('2026-11-21 09:00'); // £4.00 due each
        $alice = $this->player();

        $this->pay($alice, '3')->assertRedirect(route('admin.subs'));

        $f = $this->finance();
        $this->assertSame(300, $f->paidBy($alice->id));
        $this->assertSame(100, $f->owedBy($alice->id));
        $this->assertSame(300, $f->received());
        $this->assertSame(20 * 400 - 300, $f->owed());
    }

    public function test_money_covers_the_oldest_weeks_first(): void
    {
        $this->travelTo('2026-11-27 09:00'); // three weeks due: £6.00
        $alice = $this->player();
        $this->pay($alice, '5'); // two and a half weeks

        $f = $this->finance();
        $this->assertSame([200, 200, 100], [
            $f->covered($this->night($this->season, 1), $alice->id),
            $f->covered($this->night($this->season, 2), $alice->id),
            $f->covered($this->night($this->season, 3), $alice->id),
        ]);
        $this->assertSame([3], $f->weeksOwed($alice->id));
        $this->assertSame(100, $f->owedBy($alice->id));

        $this->pay($alice, '1.00');
        $this->assertSame([], $this->finance()->weeksOwed($alice->id));
        $this->assertSame(0, $this->finance()->owedBy($alice->id));
    }

    public function test_paying_ahead_shows_as_credit_not_a_negative_debt(): void
    {
        $this->travelTo('2026-11-14 09:00'); // £2.00 due
        $alice = $this->player();
        $this->pay($alice, '7.50');

        $f = $this->finance();
        $this->assertSame(0, $f->owedBy($alice->id));
        $this->assertSame(550, $f->creditOf($alice->id));
        $this->assertCount(19, $f->owing(), 'a credit for Alice does not reduce what others owe');

        // The credit counts towards later weeks as they come due
        $this->travelTo('2026-12-04 09:00'); // weeks 1-4 due: £8.00
        $this->assertSame(50, $this->finance()->owedBy($alice->id));
    }

    public function test_recording_a_payment_says_where_that_person_stands(): void
    {
        $this->travelTo('2026-11-21 09:00'); // £4.00 due
        $alice = $this->player();

        $this->pay($alice, '1')->assertSessionHas('status', fn ($m) => str_contains($m, 'Recorded £1.00 from Alice A') && str_contains($m, 'Now owes £3.00'));
        $this->pay($alice, '3')->assertSessionHas('status', fn ($m) => str_contains($m, 'Now paid up'));
        $this->pay($alice, '2')->assertSessionHas('status', fn ($m) => str_contains($m, '£2.00 ahead'));
    }

    public function test_payments_are_validated(): void
    {
        $alice = $this->player();
        $this->pay($alice, 'lots')->assertSessionHasErrors('amount');
        $this->pay($alice, '0')->assertSessionHasErrors('amount');
        $this->pay($alice, '-2')->assertSessionHasErrors('amount');
        $this->post(route('admin.subs.store'), ['player_id' => 9999, 'amount' => '2', 'paid_on' => '2026-11-14'])->assertNotFound();
        $this->assertSame(0, SubsPayment::count());
    }

    public function test_a_mistaken_payment_can_be_deleted(): void
    {
        $this->travelTo('2026-11-14 09:00');
        $alice = $this->player();
        $this->pay($alice, '2');
        $this->assertSame(0, $this->finance()->owedBy($alice->id));

        $this->delete(route('admin.subs.destroy', SubsPayment::first()))->assertRedirect(route('admin.subs'));
        $this->assertSame(200, $this->finance()->owedBy($alice->id));
    }

    public function test_the_subs_screen_shows_what_each_person_owes_and_recent_payments(): void
    {
        $this->travelTo('2026-11-21 09:00');
        $this->pay($this->player(), '1', '2026-11-20');

        $this->get(route('admin.subs'))->assertOk()
            ->assertSee('£3.00')->assertSee('£4.00')->assertDontSee('paid up')->assertDontSee('· owes')
            ->assertSee('Recent payments')->assertSee('20 Nov')
            ->assertSee('£1.00');
        $this->get(route('admin.subs', ['player' => $this->player('Bob B')->id]))->assertSee('selected', false);

        // The dropdown is just names, whatever people owe
        $html = $this->get(route('admin.subs'))->getContent();
        $this->assertMatchesRegularExpression('/>\s*Alice A\s*<\/option>/', $html);
        $this->assertMatchesRegularExpression('/>\s*Bob B\s*<\/option>/', $html);
    }

    public function test_finance_pages_are_public(): void
    {
        $this->travelTo('2026-11-21 09:00');
        $this->pay($this->player(), '3');
        auth()->logout();

        $this->get('/finance')->assertOk()->assertSee('Subs owed')->assertSee('Income from subs')
            ->assertSee('Alice A (£1.00 paid)', false)->assertSee('£1.00');
        $this->get('/finance/weeks/1')->assertOk()->assertSee('Not paid (19)')->assertDontSee('Record a payment');
        $this->get('/finance/weeks/2')->assertOk()->assertSee('Owes £1.00')->assertSee('part-paid');
        $this->get('/finance/weeks/3')->assertRedirect(route('finance.index')); // not due yet
        $this->get('/')->assertSee('Finance');
    }

    public function test_admins_get_links_to_record_payments_from_the_finance_pages(): void
    {
        $this->travelTo('2026-11-14 09:00');

        $this->get('/finance')->assertSee(route('admin.subs'));
        $this->get('/finance/weeks/1')->assertSee(route('admin.subs'));
    }

    public function test_finance_page_shows_balance_owing_income_and_expenses(): void
    {
        $this->travelTo('2026-11-21 09:00');
        $this->pay($this->player(), '2');
        $this->post(route('admin.expenses.store'), ['item' => 'New dartboard', 'cost' => '45', 'spent_on' => '2026-11-10'])->assertRedirect();
        auth()->logout();

        $this->get('/finance')->assertOk()
            ->assertSee('-£43.00') // £2.00 received less £45.00 spent
            ->assertSee('New dartboard')
            ->assertSee('£45.00');
    }

    public function test_owing_lists_the_biggest_debts_first(): void
    {
        $this->travelTo('2026-11-21 09:00');
        $this->pay($this->player('Alice A'), '4');
        $this->pay($this->player('Bob B'), '1');

        $owing = $this->finance()->owing();
        $this->assertCount(19, $owing);
        $this->assertSame(400, $owing->first()['amount']);
        $this->assertSame(300, $owing->last()['amount']);
        $this->assertSame('Bob B', $owing->last()['player']->name);
        $this->assertSame('weeks 1, 2', Finance::weeksText([1, 2]));
        $this->assertSame('Week 2', Finance::weeksText([2], true));
    }

    public function test_the_player_page_shows_subs_to_everyone(): void
    {
        $this->travelTo('2026-11-21 09:00');
        $alice = $this->player();
        $this->pay($alice, '3');
        auth()->logout();

        $this->get(route('players.show', $alice))->assertSeeInOrder(['Paid:', '£3.00', 'Owes:', '£1.00'])->assertDontSee('due so far');
    }

    public function test_finance_before_the_league_is_set_up(): void
    {
        Season::query()->delete();
        Setting::current()->update(['win_points' => null]);
        Season::create(['starts_on' => '2026-11-13', 'status' => 'current']);

        $this->get('/finance')->assertRedirect(route('admin.dashboard'))->assertSessionHasErrors('finance');
        $this->get(route('admin.subs'))->assertRedirect(route('admin.dashboard'));
        auth()->logout();
        $this->get('/finance')->assertRedirect(route('home'));
    }
}
