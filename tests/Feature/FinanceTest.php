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
            ->assertSee('Recent payments')->assertSee('20 Nov')->assertDontSee('Copy to send')
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

        $this->get('/finance')->assertOk()->assertSee('Balance')
            ->assertSee(route('finance.received'))->assertSee(route('finance.owed'))->assertSee(route('finance.expenses'));
        $this->get('/finance/subs-received')->assertOk()->assertSee('What each player has paid')
            ->assertSee('Alice A')->assertSee('£3.00')->assertDontSee('Week by week');
        $this->get('/finance/subs-owed')->assertOk()->assertSee('Bob B')->assertDontSee('Record a payment');
        $this->get('/finance/expenses')->assertOk()->assertSee('No expenses yet');
        $this->get('/finance/weeks/1')->assertOk()->assertSee('Not paid (19)')->assertDontSee('Record a payment');
        $this->get('/finance/weeks/2')->assertOk()->assertSee('Owes £1.00')->assertSee('part-paid');
        $this->get('/finance/weeks/3')->assertRedirect(route('finance.index')); // not due yet
        $this->get('/')->assertSee('Finance');
    }

    public function test_the_finance_page_is_only_the_headline_numbers(): void
    {
        $this->travelTo('2026-11-21 09:00');
        $this->pay($this->player(), '3');
        auth()->logout();

        $html = $this->get('/finance')->assertOk()
            ->assertSee('Subs received')->assertSee('Subs owed')->assertSee('Expenses')
            ->assertDontSee('Week by week')->assertDontSee('Bob B')->getContent();

        // The figures aren't links any more: each of these pages is linked once, from the menu
        foreach (['finance.received', 'finance.owed', 'finance.expenses'] as $name) {
            $this->assertSame(1, substr_count($html, 'href="'.route($name).'"'), $name);
        }
        $this->assertStringContainsString('class="stat-label">Subs received</div>', $html);
    }

    public function test_admins_get_links_to_record_payments_from_the_detail_pages(): void
    {
        $this->travelTo('2026-11-14 09:00');

        $this->get('/finance/subs-owed')->assertSee(route('admin.subs'));
        $this->get('/finance/weeks/1')->assertSee(route('admin.subs'));
        $this->get('/finance/expenses')->assertSee(route('admin.expenses.index'));
    }

    public function test_finance_shows_balance_and_each_detail_page_its_own_figures(): void
    {
        $this->travelTo('2026-11-21 09:00');
        $this->pay($this->player(), '2');
        $this->post(route('admin.expenses.store'), ['item' => 'New dartboard', 'cost' => '45', 'spent_on' => '2026-11-10'])->assertRedirect();
        auth()->logout();

        $this->get('/finance')->assertOk()->assertSee('-£43.00')->assertSee('£45.00')->assertSee('£2.00');
        $this->get('/finance/expenses')->assertOk()->assertSee('New dartboard')->assertSee('£45.00')->assertSee('10 Nov');
        $this->get('/finance/subs-received')->assertSee('£2.00');
    }

    public function test_the_player_page_back_link_follows_where_you_came_from(): void
    {
        $this->travelTo('2026-11-21 09:00');
        $alice = $this->player();

        $this->get(route('players.show', [$alice, 'from' => 'owed']))->assertSee('Back to subs owed')->assertSee(route('finance.owed'));
        $this->get(route('players.show', [$alice, 'from' => 'week.2']))->assertSee('Back to week 2 subs');
        $this->get(route('players.show', $alice))->assertSee('Back to league table');
    }

    public function test_subs_received_shows_each_player_with_their_total_and_the_weeks_that_are_paid(): void
    {
        $this->travelTo('2026-11-21 09:00'); // weeks 1 and 2 have been played
        $alice = $this->player();
        $this->pay($alice, '5', '2026-11-07');
        $this->pay($alice, '1.50', '2026-11-12'); // £6.50: three whole weeks, the third one ahead
        auth()->logout();

        $html = $this->get('/finance/subs-received')->assertOk()->getContent();

        $this->assertStringContainsString('£6.50', $html);
        $this->assertSame(20, substr_count($html, 'class="paid-row"'));
        $this->assertSame(19, substr_count($html, 'Nothing paid yet'));
        $this->assertSame(1, substr_count($html, 'class="week-strip"'));
        $this->assertStringContainsString('aria-label="Week 1, paid"', $html);
        $this->assertStringContainsString('aria-label="Week 2, paid"', $html);
        $this->assertStringContainsString('aria-label="Week 3, paid in advance"', $html);
        $this->assertStringNotContainsString('aria-label="Week 4', $html);
        $this->assertStringContainsString('href="'.route('finance.week', 1).'"', $html);
        $this->assertStringNotContainsString('href="'.route('finance.week', 3).'"', $html); // not played yet
        $this->assertStringContainsString(route('players.show', [$alice, 'from' => 'received']), html_entity_decode($html));
        $this->assertStringNotContainsString('Owes', $html);
        $this->assertStringNotContainsString(' on 7 Nov', $html);
    }

    public function test_every_finance_page_has_the_finance_menu_with_the_current_page_marked(): void
    {
        $this->travelTo('2026-11-21 09:00');
        auth()->logout();

        foreach ([
            '/finance' => 'Overview', '/finance/subs-received' => 'Subs received',
            '/finance/expenses' => 'Expenses', '/finance/subs-owed' => 'Subs owed', '/finance/weeks/1' => 'Subs received',
        ] as $url => $current) {
            $html = $this->get($url)->assertOk()->assertSee('aria-label="Finance"', false)->getContent();
            preg_match('/<nav class="subnav".*?<\/nav>/s', $html, $menu);
            preg_match_all('/>([^<]+)<\/a>/', $menu[0], $labels);
            $this->assertSame(['Overview', 'Subs received', 'Subs owed', 'Expenses'], $labels[1], 'menu order and wording');
            $this->assertStringContainsString('aria-current="page" >'.$current.'</a>', $html, "{$url} should mark {$current}");
        }
    }

    public function test_a_player_who_has_paid_ahead_sees_the_later_weeks_marked_paid(): void
    {
        $this->travelTo('2026-11-21 09:00'); // weeks 1 and 2 are due
        $alice = $this->player();
        $this->pay($alice, '10'); // £2 a night: five weeks

        $subs = $this->finance()->player($alice->id);
        $this->assertSame(5, $subs['paidThrough']);
        $this->assertSame([1, 2, 3, 4, 5], array_map(fn ($c) => $c['night']->week, $subs['cells']));
        $this->assertSame([false, false, true, true, true], array_map(fn ($c) => $c['ahead'], $subs['cells']));
        $this->assertSame(0, $subs['owed']);

        auth()->logout();
        $this->get(route('players.show', $alice))->assertOk()
            ->assertSee('Paid up to week 5')
            ->assertSee('Week 4, paid in advance')
            ->assertSee('Week 1, paid')
            ->assertSee('Dotted weeks')
            ->assertDontSee('Week 6, paid')
            ->assertDontSee(route('finance.week', 4)); // not played yet, so there's no page to go to
    }

    public function test_only_whole_weeks_count_as_paid_ahead(): void
    {
        $this->travelTo('2026-11-21 09:00');
        $alice = $this->player();
        $this->pay($alice, '7'); // three weeks and half of a fourth

        $this->assertSame(3, $this->finance()->player($alice->id)['paidThrough']);
        $this->assertCount(3, $this->finance()->player($alice->id)['cells']);
    }

    public function test_paying_exactly_what_is_due_shows_no_paid_ahead_message(): void
    {
        $this->travelTo('2026-11-21 09:00');
        $alice = $this->player();
        $this->pay($alice, '4');
        auth()->logout();

        $this->assertNull($this->finance()->player($alice->id)['paidThrough']);
        $this->get(route('players.show', $alice))->assertOk()->assertDontSee('Paid up to')->assertDontSee('Dotted weeks')->assertDontSee('paid in advance');
    }

    public function test_someone_who_has_paid_more_than_the_season_costs_is_told(): void
    {
        $this->travelTo('2026-11-21 09:00');
        $alice = $this->player();
        $weeks = $this->season->nights()->count();
        $this->pay($alice, (string) ($weeks * 2 + 10));

        $this->assertSame($weeks, $this->finance()->weeksCovered($alice->id));
        $this->assertSame(1000, $this->finance()->beyondSeason($alice->id));

        auth()->logout();
        $this->get(route('players.show', $alice))->assertOk()
            ->assertSee("Paid up to week {$weeks}, the end of the season")
            ->assertSee('£10.00 more than the season costs')
            ->assertSee("Week {$weeks}, paid in advance");
    }

    public function test_the_received_back_link_goes_to_subs_received(): void
    {
        $this->travelTo('2026-11-21 09:00');

        $this->get(route('players.show', [$this->player(), 'from' => 'received']))->assertSee('Back to subs received')->assertSee(route('finance.received'));
    }

    public function test_pages_showing_what_has_been_paid_say_when_subs_were_last_updated(): void
    {
        $alice = $this->player();
        $this->travelTo('2026-11-21 09:00');
        auth()->logout();
        foreach (['/finance', '/finance/subs-received', '/finance/subs-owed', '/finance/weeks/1'] as $url) {
            $this->get($url)->assertOk()->assertDontSee('Subs last updated');
        }

        $this->travelTo('2026-11-08 18:00');
        $this->actingAs($this->admin());
        $this->pay($alice, '2', '2026-11-08');
        $this->travelTo('2026-11-21 09:00');
        auth()->logout();

        foreach (['/finance', '/finance/subs-received', '/finance/subs-owed', '/finance/weeks/1', route('players.show', $alice)] as $url) {
            $this->get($url)->assertOk()->assertSee('Subs last updated 8th November');
        }
        $this->get('/finance/expenses')->assertDontSee('Subs last updated');
    }

    public function test_deleting_a_payment_also_counts_as_an_update(): void
    {
        $this->travelTo('2026-11-08 18:00');
        $this->pay($this->player(), '2', '2026-11-08');

        $this->travelTo('2026-11-12 10:00');
        $this->delete(route('admin.subs.destroy', SubsPayment::first()));
        $this->travelTo('2026-11-21 09:00');
        auth()->logout();

        $this->get('/finance')->assertSee('Subs last updated 12th November');
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
