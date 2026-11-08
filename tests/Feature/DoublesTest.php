<?php

namespace Tests\Feature;

use App\Models\Player;
use App\Models\Tournament;
use App\Models\TournamentEntrant;
use App\Models\TournamentMatch;
use App\Models\TournamentTeam;
use App\Services\Tournament\Bracket;
use App\Services\Tournament\TournamentService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\BuildsLeague;
use Tests\TestCase;

class DoublesTest extends TestCase
{
    use BuildsLeague, RefreshDatabase;

    /** Start a tournament of $n made-up people straight through the service */
    private function start(int $n = 8, ?array $defending = null, string $name = 'Doubles 2026'): Tournament
    {
        $players = [];
        foreach (range(1, $n) as $i) {
            $players[] = ['player_id' => null, 'name' => "Player {$i}", 'defending' => in_array("Player {$i}", $defending ?? [], true)];
        }

        return app(TournamentService::class)->start($name, $players, 7);
    }

    private function match(Tournament $t, int $round, int $position): TournamentMatch
    {
        return TournamentMatch::where(['tournament_id' => $t->id, 'round' => $round, 'position' => $position])->firstOrFail();
    }

    private function names(TournamentTeam $team): string
    {
        return implode(' & ', $team->names());
    }

    private function score(TournamentMatch $m, ?int $winner = null, ?int $checkout = 80)
    {
        return $this->put(route('admin.doubles.score', $m), ['winner_team_id' => $winner ?? $m->team_a_id, 'checkout' => $checkout]);
    }

    /** Play every ready match of a round, team A winning each */
    private function playRound(Tournament $t, int $round): void
    {
        foreach (TournamentMatch::where('tournament_id', $t->id)->where('round', $round)->orderBy('position')->get() as $m) {
            if ($m->fresh()->isReady() && ! $m->fresh()->isPlayed()) {
                $this->score($m->fresh());
            }
        }
    }

    /* ---- access and the link ---- */

    public function test_the_doubles_screens_need_a_login(): void
    {
        $t = $this->start(4);
        $m = $this->match($t, 1, 1);

        foreach ([['get', route('admin.doubles.index')], ['get', route('admin.doubles.create')], ['post', route('admin.doubles.store')], ['post', route('admin.doubles.redraw')],
            ['post', route('admin.doubles.end')], ['delete', route('admin.doubles.destroy')], ['get', route('admin.doubles.match', $m)],
            ['put', route('admin.doubles.score', $m)], ['delete', route('admin.doubles.clear', $m)]] as [$verb, $url]) {
            $this->{$verb}($url)->assertRedirect('/admin/login');
        }
    }

    public function test_the_quick_links_offer_to_start_one_and_then_to_manage_it(): void
    {
        $this->actingAs($this->admin());

        $this->get(route('admin.dashboard'))->assertSee('Start a doubles tournament')->assertSee(route('admin.doubles.index'));
        $this->start(4);
        $this->get(route('admin.dashboard'))->assertSee('Doubles tournament (running)')->assertDontSee('Start a doubles tournament');
    }

    /* ---- picking the players ---- */

    public function test_the_start_screen_has_a_tick_box_for_every_league_player_and_a_box_for_others(): void
    {
        $this->makePlayers(6);
        $this->actingAs($this->admin());

        $html = $this->get(route('admin.doubles.create'))->assertOk()->assertSee('Other players')->assertSee('Tick everyone')->getContent();
        $this->assertSame(6, substr_count($html, 'name="players[]"'));
        $this->assertStringContainsString('name="extras"', $html);
        $this->assertStringContainsString('value="Doubles '.today()->year.'"', $html);
        $this->assertSame(0, preg_match_all('/data-champ\s*>/', $html), 'nobody is marked as a champion to start with');
        $this->assertSame(6, preg_match_all('/data-champ\s+hidden\s*>/', $html), 'a marker is ready for each player, hidden');
        $this->assertSame(2, substr_count($html, 'name="champions[]"'), 'two optional boxes for last year’s champions');
        $this->assertStringContainsString('placeholder="Mat, Kerry"', $html);
        $this->assertStringNotContainsString('Sam Jones', $html);
        $this->assertStringNotContainsString('checked', substr($html, strpos($html, 'class="picks"')), 'nobody is ticked to start with');
    }

    public function test_starting_makes_the_tournament_from_ticked_players_and_others(): void
    {
        $players = $this->makePlayers(6);
        $this->actingAs($this->admin());

        $this->post(route('admin.doubles.store'), [
            'name' => 'Doubles 2026',
            'players' => $players->take(4)->pluck('id')->all(),
            'extras' => 'Sam Jones,  Alex   Smith ',
        ])->assertRedirect(route('admin.doubles.index'));

        $t = Tournament::running();
        $this->assertSame('Doubles 2026', $t->name);
        $this->assertCount(6, $t->entrants);
        $this->assertSame(['Alex Smith', 'Sam Jones'], $t->entrants->whereNull('player_id')->pluck('name')->sort()->values()->all());
        $this->assertSame(4, $t->entrants->whereNotNull('player_id')->count());
        $this->assertCount(3, $t->teams);
        $this->assertCount(3, $t->matches, '3 teams: a bye, a first-round match and the final');
    }

    public function test_the_extras_can_be_split_by_commas_or_new_lines_and_blanks_are_ignored(): void
    {
        $this->actingAs($this->admin());

        $this->post(route('admin.doubles.store'), ['name' => 'D', 'extras' => "One, Two,,\nThree ,   ,Four\n"]);

        $this->assertSame(['Four', 'One', 'Three', 'Two'], Tournament::running()->entrants->pluck('name')->sort()->values()->all());
    }

    public function test_it_needs_an_even_number_of_at_least_four(): void
    {
        $players = $this->makePlayers(8);
        $this->actingAs($this->admin());
        $ids = fn ($n) => $players->take($n)->pluck('id')->all();

        $this->post(route('admin.doubles.store'), ['name' => 'D', 'players' => $ids(5)])->assertSessionHasErrors('players');
        $this->post(route('admin.doubles.store'), ['name' => 'D', 'players' => $ids(2)])->assertSessionHasErrors('players');
        $this->post(route('admin.doubles.store'), ['name' => 'D', 'players' => $ids(3), 'extras' => 'A, B'])->assertSessionHasErrors('players'); // 5
        $this->post(route('admin.doubles.store'), ['name' => 'D'])->assertSessionHasErrors('players');
        $this->assertSame(0, Tournament::count());

        $this->post(route('admin.doubles.store'), ['name' => 'D', 'players' => $ids(5)])
            ->assertSessionHasErrors('players', 'Doubles needs an even number');
    }

    public function test_others_cannot_clash_with_league_players_or_each_other(): void
    {
        $players = $this->makePlayers(4);
        $this->actingAs($this->admin());

        $this->post(route('admin.doubles.store'), ['name' => 'D', 'players' => $players->take(2)->pluck('id')->all(), 'extras' => 'alice a, Zed'])
            ->assertSessionHasErrors('players');
        $this->post(route('admin.doubles.store'), ['name' => 'D', 'players' => $players->take(2)->pluck('id')->all(), 'extras' => 'Zed, zed'])
            ->assertSessionHasErrors('players');
        $this->assertSame(0, Tournament::count());
    }

    public function test_a_name_is_needed_and_a_made_up_player_id_is_refused(): void
    {
        $this->actingAs($this->admin());

        $this->post(route('admin.doubles.store'), ['name' => '', 'extras' => 'A, B, C, D'])->assertSessionHasErrors('name');
        $this->post(route('admin.doubles.store'), ['name' => 'D', 'players' => [9999], 'extras' => 'A, B, C'])->assertSessionHasErrors('players.0');
    }

    public function test_only_one_tournament_can_run_at_a_time(): void
    {
        $this->start(4);
        $this->actingAs($this->admin());

        $this->post(route('admin.doubles.store'), ['name' => 'Another', 'extras' => 'A, B, C, D'])->assertRedirect(route('admin.doubles.index'))->assertSessionHasErrors('tournament');
        $this->get(route('admin.doubles.create'))->assertRedirect(route('admin.doubles.index'));
        $this->assertSame(1, Tournament::count());
    }

    /* ---- the home page ---- */

    public function test_while_it_runs_the_home_page_is_the_tournament_and_the_league_moves_to_its_own_address(): void
    {
        $this->liveLeague();
        $this->get('/')->assertSee('League table')->assertDontSee('br-board', false);

        $this->start(8, null, 'Doubles 2026');
        $this->get('/')->assertOk()->assertSee('Doubles 2026')->assertSee('br-board', false)->assertDontSee('League table');
        $this->get('/league')->assertOk()->assertSee('League table')->assertSee('0 of 19 weeks played');

        // The nav gets a Doubles tab, and League points at /league
        $html = $this->get('/')->getContent();
        $this->assertMatchesRegularExpression('/aria-current="page" >Doubles<\/a>/', $html);
        $this->assertStringContainsString('>Doubles</a>', $html);
        $this->assertStringContainsString('href="'.route('league').'"', $html);
    }

    public function test_when_it_ends_the_league_comes_back(): void
    {
        $this->liveLeague();
        $t = $this->start(4);
        $this->actingAs($this->admin());

        $this->post(route('admin.doubles.end'))->assertRedirect(route('admin.doubles.index'));
        auth()->logout();

        $this->assertSame('ended', $t->fresh()->status);
        $this->assertNotNull($t->fresh()->ended_at);
        $this->get('/')->assertSee('League table')->assertDontSee('br-board', false);
        $this->get('/league')->assertRedirect(route('home'));
        $this->get('/')->assertDontSee('>Doubles</a>', false);
    }

    public function test_player_pages_send_you_back_to_the_league_page_during_the_tournament(): void
    {
        $this->liveLeague();
        $player = Player::first();
        $this->get(route('players.show', $player))->assertSee('href="'.route('home').'"', false);

        $this->start(4);
        $this->get(route('players.show', $player))->assertSee('href="'.route('league').'"', false)->assertSee('Back to league table');
    }

    public function test_the_tournament_shows_even_before_the_league_is_set_up(): void
    {
        $this->start(4);
        $this->get('/')->assertOk()->assertSee('Doubles 2026')->assertDontSee('Coming soon');
    }

    /* ---- the bracket ---- */

    public function test_the_bracket_has_a_card_for_every_match_in_both_layouts_and_a_trophy(): void
    {
        $t = $this->start(16); // 8 teams: 7 matches
        $html = $this->get('/')->getContent();

        $this->assertSame(7 * 2, substr_count($html, 'class="br-card '), 'every match is in the bracket and in the round-by-round list');
        $this->assertStringContainsString('class="br-half left"', $html);
        $this->assertStringContainsString('class="br-half right"', $html);
        $this->assertStringContainsString('class="trophy"', $html);
        $this->assertStringContainsString('br-final', $html);
        foreach (['Quarter-finals', 'Semi-finals', 'Final'] as $round) {
            $this->assertStringContainsString('data-round-name="'.$round.'"', $html);
        }
    }

    public function test_round_names_follow_the_size_of_the_draw(): void
    {
        $names = fn (int $players) => collect(range(1, (new Bracket($this->start($players, null, "T{$players}")))->rounds))
            ->map(fn ($r) => (new Bracket(Tournament::where('name', "T{$players}")->first()))->roundName($r))->all();

        $this->assertSame(['Final'], $names(4));
        $this->assertSame(['Semi-finals', 'Final'], $names(8));
        $this->assertSame(['Round of 16', 'Quarter-finals', 'Semi-finals', 'Final'], $names(18));
    }

    public function test_each_team_shows_both_names_and_the_defending_champions_are_marked(): void
    {
        $t = $this->start(8, ['Player 1', 'Player 2']);
        $html = $this->get('/')->getContent();

        $this->assertStringContainsString('<span class="br-name">Player 1 &amp; Player 2</span>', $html, 'a pair on one line');
        $this->assertStringNotContainsString('br-names', $html, 'no longer stacked');
        $this->assertStringContainsString('class="br-def"', $html);
        $this->assertStringContainsString('Defending champions', $html);
    }

    public function test_players_who_are_not_in_the_league_play_like_anyone_else(): void
    {
        $this->actingAs($this->admin());
        $this->post(route('admin.doubles.store'), ['name' => 'D', 'extras' => 'Sam Jones, Alex Smith, Pat Lee, Kim Doe']);
        auth()->logout();

        $this->get('/')->assertSee('Sam Jones')->assertSee('Kim Doe');
    }

    public function test_byes_show_as_byes_and_the_team_is_already_in_round_two(): void
    {
        $t = $this->start(6); // 3 teams: one bye
        $bye = TournamentMatch::where('tournament_id', $t->id)->where('is_bye', true)->first();
        $final = $this->match($t, 2, 1);

        $this->assertSame($bye->team_a_id, $bye->winner_team_id);
        $this->assertContains($bye->team_a_id, [$final->team_a_id, $final->team_b_id], 'straight into the final');
        $this->get('/')->assertSee('Bye');
    }

    /* ---- scoring ---- */

    public function test_guests_see_no_score_links_but_the_admin_can_click_a_match(): void
    {
        $t = $this->start(8);

        $this->get('/')->assertDontSee('/admin/doubles/matches', false)->assertDontSee('Add result');

        $this->actingAs($this->admin());
        $html = $this->get('/')->getContent();
        $this->assertStringContainsString('/admin/doubles/matches/', $html);
        $this->assertStringContainsString('Add result', $html);
        $this->assertSame(2 * 2, substr_count($html, 'href="'.url('/admin/doubles/matches').'/'.$this->match($t, 1, 1)->id) + substr_count($html, 'href="'.url('/admin/doubles/matches').'/'.$this->match($t, 1, 2)->id), 'a link per match in each layout');
    }

    public function test_only_matches_with_both_teams_known_can_be_clicked(): void
    {
        $t = $this->start(8);
        $this->actingAs($this->admin());

        $this->assertStringNotContainsString('matches/'.$this->match($t, 2, 1)->id.'?', $this->get('/')->getContent(), 'the semi-final is still waiting');
        $this->get(route('admin.doubles.match', $this->match($t, 2, 1)))->assertRedirect()->assertSessionHasErrors('match');
    }

    public function test_the_score_screen_shows_the_two_teams(): void
    {
        $t = $this->start(8);
        $m = $this->match($t, 1, 1);
        $bracket = new Bracket($t);
        $this->actingAs($this->admin());

        $this->get(route('admin.doubles.match', [$m, 'back' => '/']))->assertOk()
            ->assertSee($this->names($bracket->team($m->team_a_id)))->assertSee($this->names($bracket->team($m->team_b_id)))
            ->assertSee('Who won?')->assertSee('Winning checkout (optional)')->assertDontSee('Clear this result');
    }

    public function test_a_winner_goes_into_the_next_round(): void
    {
        $t = $this->start(8);
        $this->actingAs($this->admin());
        $m = $this->match($t, 1, 1);

        $this->score($m, $m->team_b_id, 96)->assertRedirect()->assertSessionHas('status');

        $m->refresh();
        $this->assertSame([$m->team_b_id, 96], [$m->winner_team_id, $m->checkout]);
        $this->assertSame($m->team_b_id, $this->match($t, 2, 1)->team_a_id, 'match 1 feeds the top slot');

        $m2 = $this->match($t, 1, 2);
        $this->score($m2, $m2->team_a_id);
        $this->assertSame($m2->team_a_id, $this->match($t, 2, 1)->team_b_id, 'match 2 feeds the bottom slot');
        $this->assertTrue($this->match($t, 2, 1)->isReady());
    }

    public function test_the_checkout_is_optional_but_checked_when_given(): void
    {
        $t = $this->start(8);
        $this->actingAs($this->admin());
        $m = $this->match($t, 1, 1);

        $this->score($m, null, null)->assertSessionHasNoErrors();
        $this->assertNull($m->fresh()->checkout);
        $this->score($m->fresh(), null, 171)->assertSessionHasErrors('checkout');
        $this->score($m->fresh(), null, 1)->assertSessionHasErrors('checkout');
        $this->put(route('admin.doubles.score', $m), ['winner_team_id' => $m->team_a_id, 'checkout' => 'x'])->assertSessionHasErrors('checkout');
        $this->score($m->fresh(), null, 170)->assertSessionHasNoErrors();
    }

    public function test_the_winner_must_be_one_of_the_two_teams(): void
    {
        $t = $this->start(8);
        $this->actingAs($this->admin());
        $m = $this->match($t, 1, 1);
        $other = $this->match($t, 1, 2);

        $this->put(route('admin.doubles.score', $m), ['winner_team_id' => $other->team_a_id])->assertSessionHasErrors('winner_team_id');
        $this->put(route('admin.doubles.score', $m), [])->assertSessionHasErrors('winner_team_id');
        $this->assertNull($m->fresh()->winner_team_id);
    }

    public function test_a_result_can_be_changed_until_the_next_round_is_played(): void
    {
        $t = $this->start(8);
        $this->actingAs($this->admin());
        $a = $this->match($t, 1, 1);
        $b = $this->match($t, 1, 2);
        $this->score($a, $a->team_a_id);
        $this->score($b, $b->team_a_id);

        $this->score($a->fresh(), $a->team_b_id)->assertSessionHasNoErrors();
        $this->assertSame($a->team_b_id, $this->match($t, 2, 1)->team_a_id, 'the new winner moves on');

        $semi = $this->match($t, 2, 1);
        $this->score($semi);
        $this->score($a->fresh(), $a->team_a_id)->assertSessionHasErrors('winner_team_id'); // semi-final played
        $this->assertSame($a->team_b_id, $a->fresh()->winner_team_id);
        $this->score($a->fresh(), $a->team_b_id, 33)->assertSessionHasNoErrors(); // same winner, new checkout is fine
        $this->assertSame(33, $a->fresh()->checkout);
    }

    public function test_a_result_can_be_cleared_until_the_next_round_is_played(): void
    {
        $t = $this->start(8);
        $this->actingAs($this->admin());
        $a = $this->match($t, 1, 1);
        $b = $this->match($t, 1, 2);
        $this->score($a);
        $this->score($b);

        $this->get(route('admin.doubles.match', $a->fresh()))->assertSee('Clear this result');

        $this->score($this->match($t, 2, 1));
        $this->delete(route('admin.doubles.clear', $a->fresh()))->assertSessionHasErrors('winner_team_id');
        $this->assertNotNull($a->fresh()->winner_team_id);

        $this->delete(route('admin.doubles.clear', $this->match($t, 2, 1)))->assertSessionHasNoErrors(); // clear the semi first
        $this->delete(route('admin.doubles.clear', $a->fresh()))->assertSessionHasNoErrors();
        $a->refresh();
        $this->assertNull($a->winner_team_id);
        $this->assertNull($a->checkout);
        $this->assertNull($this->match($t, 2, 1)->team_a_id, 'and they are out of the next match again');
    }

    public function test_a_bye_cannot_be_scored_or_cleared(): void
    {
        $t = $this->start(6);
        $bye = TournamentMatch::where('tournament_id', $t->id)->where('is_bye', true)->first();
        $this->actingAs($this->admin());

        $this->get(route('admin.doubles.match', $bye))->assertRedirect()->assertSessionHasErrors('match');
        $this->put(route('admin.doubles.score', $bye), ['winner_team_id' => $bye->team_a_id])->assertSessionHasErrors();
        $this->delete(route('admin.doubles.clear', $bye))->assertSessionHasErrors('winner_team_id');
        $this->assertSame($bye->team_a_id, $bye->fresh()->winner_team_id, 'unchanged');
    }

    public function test_the_back_link_cannot_leave_the_site(): void
    {
        $t = $this->start(8);
        $this->actingAs($this->admin());
        $m = $this->match($t, 1, 1);

        $this->put(route('admin.doubles.score', $m), ['winner_team_id' => $m->team_a_id, 'back' => '//evil.example'])
            ->assertRedirect(route('admin.doubles.index', [], false));
        $this->put(route('admin.doubles.score', $m), ['winner_team_id' => $m->team_a_id, 'back' => '/'])->assertRedirect('/');
    }

    public function test_a_match_from_another_tournament_is_not_found(): void
    {
        $old = $this->start(4, null, 'Old');
        app(TournamentService::class)->end($old);
        $this->start(4, null, 'New');
        $this->actingAs($this->admin());

        $this->get(route('admin.doubles.match', $this->match($old, 1, 1)))->assertNotFound();
    }

    public function test_scores_show_on_the_public_page_as_they_are_entered(): void
    {
        $t = $this->start(8);
        $this->actingAs($this->admin());
        $m = $this->match($t, 1, 1);
        $this->score($m, $m->team_a_id, 121);
        auth()->logout();

        $html = $this->get('/')->getContent();
        $this->assertStringContainsString('Checkout 121', $html);
        $this->assertStringContainsString('1 of 3 matches played', $html);
        $this->assertMatchesRegularExpression('/br-team\s+win/', $html);
        $this->assertMatchesRegularExpression('/br-team\s+lose/', $html);
    }

    /* ---- champions ---- */

    public function test_the_final_decides_the_champions(): void
    {
        $t = $this->start(8);
        $this->actingAs($this->admin());
        $this->get(route('admin.doubles.index'))->assertDontSee('Champions');

        $this->playRound($t, 1); // 8 players are 4 teams: two semi-finals, then the final
        $final = $this->match($t, 2, 1);
        $this->score($final, $final->team_b_id);

        $champions = (new Bracket($t))->champions();
        $this->assertSame($final->team_b_id, $champions->id);
        $this->get(route('admin.doubles.index'))->assertSee('Champions')->assertSee($this->names($champions));
        auth()->logout();
        $this->get('/')->assertSee('Champions')->assertSee('class="br-winner done"', false);
    }

    public function test_finished_tournaments_are_listed_with_their_champions(): void
    {
        $t = $this->start(4, null, 'Doubles 2025');
        $this->actingAs($this->admin());
        $final = $this->match($t, 1, 1);
        $this->score($final);
        $this->post(route('admin.doubles.end'));

        $this->get(route('admin.doubles.index'))->assertSee('Past tournaments')->assertSee('Doubles 2025')
            ->assertSee($this->names((new Bracket($t))->champions()))->assertSee('Start a doubles tournament');
    }

    public function test_last_years_champions_are_ticked_marked_and_kept_together(): void
    {
        $players = $this->makePlayers(6);
        $this->actingAs($this->admin());

        // Last year: Alice A, Bob B, Cara C and a guest. Whoever shares a team with the guest wins.
        $this->post(route('admin.doubles.store'), ['name' => 'Doubles 2025', 'players' => $players->take(3)->pluck('id')->all(), 'extras' => 'Guest G']);
        $last = Tournament::running();
        $winner = $last->teams->first(fn ($team) => in_array('Guest G', $team->names(), true));
        $this->score($this->match($last, 1, 1), $winner->id);
        $partner = collect($winner->names())->reject(fn ($n) => $n === 'Guest G')->first();
        $this->post(route('admin.doubles.end'));

        // This year's start screen: the champions are filled in, the league one is ticked and marked, the guest is waiting in the other box
        $html = $this->get(route('admin.doubles.create'))->getContent();
        $partnerId = Player::where('name', $partner)->value('id');
        $this->assertMatchesRegularExpression('/<input type="checkbox" name="players\[\]" value="'.$partnerId.'" checked/', $html);
        $this->assertSame(1, preg_match_all('/data-champ\s*>/', $html), 'one marker shown, on the league champion');
        $this->assertSame(1, substr_count($html, ' checked'), 'only the champion is ticked');
        $this->assertSame(2, preg_match_all('/name="champions\[\]" value="('.preg_quote($partner, '/').'|Guest G)"/', $html));
        $this->assertStringContainsString('name="extras" value="Guest G"', $html);

        // Start with both of them and some others: they're one team
        $others = $players->reject(fn ($p) => $p->name === $partner)->take(4)->pluck('id')->all(); // with the champion and the guest: 6
        $this->post(route('admin.doubles.store'), ['name' => 'Doubles 2026', 'champions' => [$partner, 'Guest G'], 'players' => array_merge([$partnerId], $others), 'extras' => 'Guest G'])->assertSessionHasNoErrors();

        $team = Tournament::running()->teams->first(fn ($t) => $t->defending);
        $this->assertEqualsCanonicalizing([$partner, 'Guest G'], $team->names());
        $this->assertSame(2, TournamentEntrant::where('tournament_id', Tournament::running()->id)->where('defending', true)->count());
        $this->get('/')->assertSee('Defending champions');
    }

    public function test_a_champion_who_does_not_turn_up_does_not_hold_anyone_up(): void
    {
        $players = $this->makePlayers(6);
        $this->actingAs($this->admin());
        $this->post(route('admin.doubles.store'), ['name' => 'Doubles 2025', 'players' => $players->take(4)->pluck('id')->all()]);
        $last = Tournament::running();
        $winner = $this->match($last, 1, 1);
        $this->score($winner->fresh(), $winner->team_a_id);
        $champions = (new Bracket($last))->champions()->names();
        $this->post(route('admin.doubles.end'));

        // Only one of the two is ticked now
        $present = Player::where('name', $champions[0])->value('id');
        $rest = $players->reject(fn ($p) => in_array($p->name, $champions, true))->take(3)->pluck('id')->all();
        $this->post(route('admin.doubles.store'), ['name' => 'Doubles 2026', 'champions' => $champions, 'players' => array_merge([$present], $rest)])->assertSessionHasNoErrors();

        $t = Tournament::running();
        $this->assertCount(0, $t->teams->where('defending', true), 'a lone champion is just another player');
        $this->assertCount(2, $t->teams);
    }

    public function test_the_start_screen_keeps_what_you_ticked_when_there_is_an_error(): void
    {
        $players = $this->makePlayers(5);
        $this->actingAs($this->admin());

        $this->from(route('admin.doubles.create'))->post(route('admin.doubles.store'), ['name' => 'My name', 'players' => $players->take(3)->pluck('id')->all(), 'extras' => 'Zed, Yan'])
            ->assertRedirect();
        $html = $this->get(route('admin.doubles.create'))->getContent();

        $this->assertSame(3, substr_count(substr($html, strpos($html, 'class="picks"')), ' checked'));
        $this->assertStringContainsString('value="My name"', $html);
        $this->assertStringContainsString('value="Zed, Yan"', $html);
    }

    /* ---- running it ---- */

    public function test_the_draw_can_be_redone_until_a_match_is_played(): void
    {
        $t = $this->start(12);
        $this->actingAs($this->admin());
        $before = $t->teams->map(fn ($team) => $this->names($team))->sort()->values()->all();

        $this->get(route('admin.doubles.index'))->assertSee('Draw again');
        $this->post(route('admin.doubles.redraw'))->assertRedirect(route('admin.doubles.index'))->assertSessionHas('status', 'Drawn again.');

        $t->refresh();
        $this->assertCount(6, $t->teams()->get());
        $this->assertCount(7, $t->matches()->get());
        $this->assertSame(12, $t->entrants()->count());
        $teamsNow = $t->teams()->with(['entrantA', 'entrantB'])->get()->map(fn ($team) => $this->names($team))->sort()->values()->all();
        $this->assertNotSame($before, $teamsNow, 'a fresh draw (the odds of the same one are tiny)');

        $m = $this->match($t, 1, 1);
        while (! $m->isReady()) {
            $m = $this->match($t, 1, $m->position + 1);
        }
        $this->score($m);
        $this->get(route('admin.doubles.index'))->assertDontSee('Draw again');
        $this->post(route('admin.doubles.redraw'))->assertSessionHasErrors('tournament');
    }

    public function test_the_admin_page_shows_progress_and_the_bracket(): void
    {
        $t = $this->start(8, null, 'Doubles 2026');
        $this->actingAs($this->admin());

        $this->get(route('admin.doubles.index'))->assertOk()->assertSee('Doubles 2026: 4 teams, 0 of 3 matches played')
            ->assertSee('data-bracket-single', false)->assertDontSee('br-board', false)->assertSee('>Finish</button>', false)->assertDontSee('Finish and show the league again');
    }

    public function test_a_tournament_can_be_deleted_with_everything_in_it(): void
    {
        $t = $this->start(8);
        $this->actingAs($this->admin());

        $this->delete(route('admin.doubles.destroy'))->assertRedirect(route('admin.doubles.index'));

        foreach ([Tournament::class, TournamentEntrant::class, TournamentTeam::class, TournamentMatch::class] as $model) {
            $this->assertSame(0, $model::count(), $model.' should be empty');
        }
        $this->get('/')->assertDontSee('br-board', false);
    }

    public function test_the_players_are_kept_when_a_tournament_is_deleted(): void
    {
        $this->makePlayers(4);
        $this->actingAs($this->admin());
        $this->post(route('admin.doubles.store'), ['name' => 'D', 'players' => Player::pluck('id')->all()]);
        $this->delete(route('admin.doubles.destroy'));

        $this->assertSame(4, Player::count());
    }

    public function test_the_start_screen_script_counts_players_and_teams(): void
    {
        $js = file_get_contents(public_path('js/admin.js'));

        $this->assertStringContainsString('form[data-doubles]', $js);
        $this->assertStringContainsString('data-tick', $js);
        $this->assertStringContainsString("' teams'", $js);
    }

    /* ---- defending champions can be set by hand, and are optional ---- */

    public function test_the_champions_can_be_set_by_hand_with_no_earlier_tournament(): void
    {
        $players = $this->makePlayers(4);
        $this->actingAs($this->admin());

        $this->post(route('admin.doubles.store'), ['name' => 'D', 'champions' => ['alice a', 'Zed'], 'players' => $players->pluck('id')->take(3)->all(), 'extras' => 'Zed'])->assertSessionHasNoErrors();

        $t = Tournament::running();
        $team = $t->teams->first(fn ($team) => $team->defending);
        $this->assertEqualsCanonicalizing(['Alice A', 'Zed'], $team->names(), 'matched without minding capitals, and kept together');
        $this->assertSame(2, $t->entrants->where('defending', true)->count());
        $this->get('/')->assertSee('Defending champions');
    }

    public function test_leaving_the_champions_empty_is_fine(): void
    {
        $this->actingAs($this->admin());

        $this->post(route('admin.doubles.store'), ['name' => 'D', 'champions' => ['', ''], 'extras' => 'A, B, C, D'])->assertSessionHasNoErrors();
        $this->assertCount(0, Tournament::running()->teams->where('defending', true));

        app(TournamentService::class)->delete(Tournament::running());
        $this->post(route('admin.doubles.store'), ['name' => 'D', 'extras' => 'A, B, C, D'])->assertSessionHasNoErrors();
        $this->assertCount(2, Tournament::running()->teams);
    }

    public function test_champions_who_are_not_playing_are_ignored(): void
    {
        $this->actingAs($this->admin());

        $this->post(route('admin.doubles.store'), ['name' => 'D', 'champions' => ['Nobody N', 'Absent A'], 'extras' => 'A, B, C, D'])->assertSessionHasNoErrors();

        $t = Tournament::running();
        $this->assertCount(0, $t->teams->where('defending', true));
        $this->assertSame(0, $t->entrants->where('defending', true)->count());
        $this->assertCount(4, $t->entrants, 'they were not added as players');
    }

    public function test_one_champion_on_their_own_is_marked_but_not_paired(): void
    {
        $this->actingAs($this->admin());

        $this->post(route('admin.doubles.store'), ['name' => 'D', 'champions' => ['A', ''], 'extras' => 'A, B, C, D'])->assertSessionHasNoErrors();

        $t = Tournament::running();
        $this->assertCount(0, $t->teams->where('defending', true));
        $this->assertCount(2, $t->teams);
    }

    public function test_the_champions_must_be_two_different_people(): void
    {
        $this->actingAs($this->admin());

        $this->post(route('admin.doubles.store'), ['name' => 'D', 'champions' => ['A', 'a'], 'extras' => 'A, B, C, D'])->assertSessionHasErrors('players');
        $this->post(route('admin.doubles.store'), ['name' => 'D', 'champions' => ['A', 'B', 'C'], 'extras' => 'A, B, C, D'])->assertSessionHasErrors('champions');
        $this->assertSame(0, Tournament::count());
    }

    public function test_the_champion_boxes_start_empty_with_no_earlier_tournament_and_filled_after_one(): void
    {
        $this->actingAs($this->admin());
        $html = $this->get(route('admin.doubles.create'))->getContent();
        $this->assertSame(2, preg_match_all('/name="champions\[\]" value=""/', $html));

        $t = $this->start(4, null, 'Doubles 2025');
        $final = $this->match($t, 1, 1);
        $this->score($final);
        $names = (new Bracket($t))->champions()->names();
        $this->post(route('admin.doubles.end'));

        $html = $this->get(route('admin.doubles.create'))->getContent();
        foreach ($names as $n) {
            $this->assertStringContainsString('name="champions[]" value="'.$n.'"', $html);
        }
    }

    public function test_the_champions_can_be_cleared_even_when_last_years_are_filled_in(): void
    {
        $t = $this->start(4, null, 'Doubles 2025');
        $this->actingAs($this->admin());
        $this->score($this->match($t, 1, 1));
        $names = (new Bracket($t))->champions()->names();
        $this->post(route('admin.doubles.end'));

        // The admin empties the boxes and starts with the same people anyway
        $this->post(route('admin.doubles.store'), ['name' => 'D', 'champions' => ['', ''], 'extras' => implode(', ', $names).', X, Y'])->assertSessionHasNoErrors();

        $this->assertCount(0, Tournament::running()->teams->where('defending', true));
    }

    public function test_the_script_ticks_marks_and_adds_champions_as_they_are_entered(): void
    {
        $js = file_get_contents(public_path('js/admin.js'));

        foreach (['[data-champion]', "'[data-champ]'", 'const bring', 'const mark', "matches('[data-champion]')"] as $bit) {
            $this->assertStringContainsString($bit, $js, $bit);
        }
    }

    /* ---- layout and wording ---- */

    public function test_the_winner_box_is_under_the_trophy(): void
    {
        $t = $this->start(8);
        $html = $this->get('/')->getContent();

        $final = strpos($html, 'br-final');
        $trophy = strpos($html, 'class="br-trophy">');
        $winner = strpos($html, 'class="br-winner');
        $this->assertTrue($final < $trophy && $trophy < $winner, 'final, then the trophy, then the winner');
        $this->assertStringContainsString('class="br-below"', $html);
    }

    public function test_the_winner_box_is_under_the_trophy_in_the_admin_too(): void
    {
        $this->start(8);
        $this->actingAs($this->admin());
        $html = $this->get(route('admin.doubles.index'))->getContent();

        $this->assertLessThan(strpos($html, 'class="br-winner'), strpos($html, 'class="br-trophy">'));
    }

    public function test_the_finish_button_is_just_finish_with_room_above_the_note(): void
    {
        $this->start(8);
        $this->actingAs($this->admin());
        $html = $this->get(route('admin.doubles.index'))->getContent();

        $this->assertMatchesRegularExpression('/>Finish<\/button>/', $html);
        $this->assertMatchesRegularExpression('/>Draw again<\/button>.*?<p class="hint" style="margin: 20px 0 0">No match has been played/s', $html);
    }

    /* ---- the one-sided bracket: phones, and the admin at every size ---- */

    public function test_teams_are_shown_on_one_line_as_a_and_b(): void
    {
        $this->actingAs($this->admin());
        $this->post(route('admin.doubles.store'), ['name' => 'D', 'extras' => 'Linda L, Jayme J, Mat M, Kerry K']);
        auth()->logout();
        $t = Tournament::running();
        $team = $t->teams()->with(['entrantA', 'entrantB'])->first();

        $html = $this->get('/')->getContent();
        $this->assertStringContainsString('<span class="br-name">'.e($team->label()).'</span>', $html);
        $this->assertMatchesRegularExpression('/[A-Za-z]+ [A-Z] &amp; [A-Za-z]+ [A-Z]/', $html);
        $this->assertStringContainsString($team->entrantA->name.' &amp; '.$team->entrantB->name, $html);
    }

    public function test_the_front_end_has_both_views_and_the_admin_only_the_one_sided_one(): void
    {
        $t = $this->start(16);

        $front = $this->get('/')->getContent();
        $this->assertStringContainsString('class="br-board', $front, 'wide screens: two-sided');
        $this->assertMatchesRegularExpression('/<div class="bs " data-bracket-single>/', $front, 'phones: one-sided, hidden on wide screens');

        $this->actingAs($this->admin());
        $admin = $this->get(route('admin.doubles.index'))->getContent();
        $this->assertStringNotContainsString('br-board', $admin);
        $this->assertMatchesRegularExpression('/<div class="bs always" data-bracket-single>/', $admin, 'the admin always gets it');
        $this->assertSame(7, substr_count($admin, 'class="br-card '), 'one card per match, once');
    }

    public function test_the_one_sided_view_has_every_round_then_a_winner_step_with_arrows(): void
    {
        $t = $this->start(16); // 8 teams: quarter-finals, semi-finals, final
        $html = $this->get('/')->getContent();
        $single = substr($html, strpos($html, 'data-bracket-single'));

        $this->assertStringContainsString('data-bs="prev"', $single);
        $this->assertStringContainsString('data-bs="next"', $single);
        $this->assertStringContainsString('aria-label="Previous round"', $single);
        $this->assertStringContainsString('aria-label="Next round"', $single);
        $this->assertSame(4, substr_count($single, 'data-round-name="Quarter-finals"'));
        $this->assertSame(2, substr_count($single, 'data-round-name="Semi-finals"'));
        $this->assertSame(1, substr_count($single, 'data-round-name="Final"'));
        $this->assertStringContainsString('class="bs-end" data-round="end" data-round-name="Winner"', $single);
        $this->assertLessThan(strpos($single, 'class="bs-end"'), strpos($single, 'data-round-name="Final"'), 'the winner step follows the final');
        $this->assertStringContainsString('>Quarter-finals</div>', $single, 'the title starts on the first round');
    }

    public function test_the_winner_step_says_champions_once_it_is_decided(): void
    {
        $t = $this->start(4);
        $this->actingAs($this->admin());
        $this->score($this->match($t, 1, 1));
        auth()->logout();

        $this->assertStringContainsString('class="bs-end" data-round="end" data-round-name="Champions"', $this->get('/')->getContent());
    }

    public function test_on_phones_one_round_fills_the_screen_and_the_admin_stays_visible_on_wide_screens(): void
    {
        $js = file_get_contents(public_path('js/bracket.js'));
        foreach (["matchMedia('(max-width: 899px)')", "setProperty('--cw'", "setProperty('--gap'", "'data-snap'", "classList.add('paged')", 'keys.sort', "'end'"] as $bit) {
            $this->assertStringContainsString($bit, $js, $bit);
        }

        $css = file_get_contents(public_path('css/app.css'));
        $this->assertStringContainsString('.bs.paged .bs-scroll { scroll-snap-type: x mandatory', $css);
        $this->assertStringContainsString('.bs.paged [data-snap] { scroll-snap-align: start', $css);
        $this->assertStringContainsString('.bs:not(.always) { display: none; }', $css, 'the front end hides it on wide screens; the admin (always) does not');
        $this->assertMatchesRegularExpression('/@media \(min-width: 900px\) \{[^@]*\.bs:not\(\.always\)/s', $css);
    }
}
