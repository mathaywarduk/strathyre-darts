<?php

namespace Tests\Feature;

use App\Models\Fixture;
use App\Models\LeagueNight;
use App\Models\Player;
use App\Models\Season;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Tests\Support\BuildsLeague;
use Tests\TestCase;

class PlayersAndFixturesTest extends TestCase
{
    use BuildsLeague, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->actingAs($this->admin());
        Season::create(['starts_on' => '2026-11-13', 'status' => 'current']); // as the seeder does
    }

    public function test_player_csv_is_checked_before_anything_is_saved(): void
    {
        $csv = "Name,Ranking,Gender\nFiona M,4,W\nCallum F,5,M\n";
        $this->post(route('admin.players.preview'), ['text' => $csv])->assertRedirect(route('admin.players.index'));
        $this->assertSame(0, Player::count());

        $this->get(route('admin.players.index'))->assertSee('Fiona M')->assertSee('Save 2 players');
        $this->post(route('admin.players.confirm'))->assertRedirect();
        $this->assertSame(2, Player::count());
        $this->assertSame('W', Player::where('name', 'Fiona M')->value('gender'));
    }

    public function test_player_csv_can_be_uploaded_as_a_file(): void
    {
        $file = UploadedFile::fake()->createWithContent('players.csv', "Fiona M,4,W\nCallum F,5,m\n");
        $this->post(route('admin.players.preview'), ['csv' => $file])->assertRedirect(route('admin.players.index'));
        $this->post(route('admin.players.confirm'));
        $this->assertSame('M', Player::where('name', 'Callum F')->value('gender'));
    }

    public function test_player_csv_errors_are_shown_and_nothing_is_saved(): void
    {
        $csv = "Name,Ranking,Gender\nFiona M,6,W\nCallum F,5,X\n,3,M\nRoss K,2,M\nross k,3,M\n";
        $this->post(route('admin.players.preview'), ['text' => $csv])->assertRedirect()
            ->assertSessionHas('import_errors', fn ($e) => count($e) === 4);
        $this->assertSame(0, Player::count());
        $this->assertNull(session('players_import'));
    }

    public function test_uploading_again_updates_players_by_name(): void
    {
        Player::create(['name' => 'Fiona M', 'ranking' => 2, 'gender' => 'W']);
        $this->post(route('admin.players.preview'), ['text' => "fiona m,5,W\nNew Person,1,M"]);
        $this->post(route('admin.players.confirm'));

        $this->assertSame(2, Player::count());
        $this->assertSame(5, Player::where('name', 'fiona m')->value('ranking'));
    }

    public function test_replacing_a_player_is_just_editing_their_name(): void
    {
        Season::query()->delete();
        $season = $this->liveLeague();
        $old = Player::first();
        $this->put(route('admin.players.update', $old), ['name' => 'Replacement R', 'ranking' => 3, 'gender' => 'M'])->assertRedirect();

        $this->assertSame('Replacement R', $old->fresh()->name);
        $this->assertSame(19, Fixture::where('player1_id', $old->id)->orWhere('player2_id', $old->id)->count());
        $this->delete(route('admin.players.destroy', $old))->assertSessionHasErrors('player');
        $this->put(route('admin.players.update', $old), ['name' => 'bob b', 'ranking' => 3, 'gender' => 'M'])->assertSessionHasErrors('name');
    }

    public function test_generating_fixtures_previews_then_saves(): void
    {
        $this->makePlayers(20);

        $response = $this->post(route('admin.fixtures.generate'), ['start_on' => '2026-11-13'])->assertOk();
        $response->assertSee('19 weeks, 190 matches')->assertSee('Friday 13 November');
        $this->assertSame(0, Fixture::count(), 'nothing saved by the preview');

        $token = session('fixture_draft.token');
        $this->post(route('admin.fixtures.save'), ['token' => 'wrong'])->assertSessionHasErrors('draft');
        $this->post(route('admin.fixtures.save'), ['token' => $token])->assertRedirect(route('admin.fixtures.index'));

        $this->assertSame(190, Fixture::count());
        $this->assertSame(19, LeagueNight::count());
        $this->assertSame('2026-11-13', LeagueNight::where('week', 1)->first()->date->toDateString());
        $this->assertSame('2027-03-19', LeagueNight::where('week', 19)->first()->date->toDateString());
    }

    public function test_weeks_off_are_skipped_and_dates_must_be_fridays(): void
    {
        $this->makePlayers(20);

        $this->post(route('admin.fixtures.generate'), ['start_on' => '2026-11-14'])->assertSessionHasErrors('start_on');

        $this->post(route('admin.fixtures.generate'), ['start_on' => '2026-11-13', 'off' => ['2026-12-25', '2027-01-01']])->assertOk();
        $this->post(route('admin.fixtures.save'), ['token' => session('fixture_draft.token')]);

        $dates = LeagueNight::orderBy('week')->get()->map(fn ($n) => $n->date->toDateString());
        $this->assertNotContains('2026-12-25', $dates);
        $this->assertNotContains('2027-01-01', $dates);
        $this->assertSame('2026-12-18', $dates[5]);
        $this->assertSame('2027-01-08', $dates[6]);
        $this->assertSame('2027-04-02', $dates[18]);
    }

    public function test_a_maximum_number_of_weeks_makes_doubles(): void
    {
        $this->makePlayers(20);

        $this->post(route('admin.fixtures.generate'), ['start_on' => '2026-11-13', 'max_weeks' => 15])->assertOk()->assertSee('twice on a night');
        $this->post(route('admin.fixtures.generate'), ['start_on' => '2026-11-13', 'max_weeks' => 4])->assertSessionHasErrors('max_weeks');
    }

    public function test_odd_numbers_show_byes(): void
    {
        $this->makePlayers(19);

        $this->post(route('admin.fixtures.generate'), ['start_on' => '2026-11-13'])->assertOk()->assertSee('19 weeks, 171 matches')->assertSee('Bye:');
    }

    public function test_manual_fixtures_csv(): void
    {
        $this->makePlayers(4);
        $csv = "Week,Date,Player 1,Player 2\n1,2026-11-13,Alice A,Bob B\n1,13/11/2026,Cara C,Dan D\n2,2026-11-20,Alice A,Cara C\n2,2026-11-20,bob b,dan d\n3,2026-11-27,Alice A,Dan D\n3,2026-11-27,Bob B,Cara C\n";

        $this->post(route('admin.fixtures.manual'), ['text' => $csv])->assertOk()->assertSee('3 weeks, 6 matches');
        $this->post(route('admin.fixtures.save'), ['token' => session('fixture_draft.token')]);

        $this->assertSame(6, Fixture::count());
        $this->assertSame(3, LeagueNight::count());
    }

    public function test_manual_fixtures_csv_errors(): void
    {
        $this->makePlayers(4);
        $csv = "1,2026-11-13,Alice A,Bob B\n1,2026-11-14,Cara C,Dan D\n2,notadate,Alice A,Cara C\n2,2026-11-20,Bob B,Alice A\n4,2026-12-04,Alice A,Nobody\n";

        $this->post(route('admin.fixtures.manual'), ['text' => $csv])->assertRedirect()
            ->assertSessionHas('import_errors', function ($e) {
                $text = implode(' | ', $e);

                return str_contains($text, 'different date') && str_contains($text, 'date must look like')
                    && str_contains($text, 'already down to play') && str_contains($text, 'no player called');
            });
        $this->assertSame(0, Fixture::count());

        $gaps = "1,2026-11-13,Alice A,Bob B\n3,2026-11-27,Cara C,Dan D\n";
        $this->post(route('admin.fixtures.manual'), ['text' => $gaps])->assertSessionHas('import_errors', fn ($e) => str_contains(implode($e), 'Missing: 2'));
    }

    public function test_fixtures_lock_once_results_exist(): void
    {
        Season::query()->delete();
        $season = $this->liveLeague();
        [$f, $a, $b] = $this->firstMatch($this->night($season, 1));
        $this->submitLines($this->night($season, 1), "{$a} beat {$b} 15");

        $this->post(route('admin.fixtures.generate'), ['start_on' => '2026-11-13'])->assertSessionHasErrors('draft');
        $this->get(route('admin.fixtures.index'))->assertSee('fixtures are locked');
    }

    public function test_a_night_can_be_moved_to_another_day(): void
    {
        Season::query()->delete();
        $season = $this->liveLeague();
        $night = $this->night($season, 7);

        $this->put(route('admin.fixtures.night', $night), ['date' => '2026-12-24'])->assertRedirect();
        $this->assertSame('2026-12-24', $night->fresh()->date->toDateString());
    }
}
