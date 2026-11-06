<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\BuildsLeague;
use Tests\TestCase;

class AccessTest extends TestCase
{
    use BuildsLeague, RefreshDatabase;

    public function test_admin_pages_need_a_login(): void
    {
        $this->get('/admin')->assertRedirect('/admin/login');
        $this->get('/admin/results')->assertRedirect('/admin/login');
        $this->get('/admin/players')->assertRedirect('/admin/login');
        $this->post('/admin/season/reset', [])->assertRedirect('/admin/login');
    }

    public function test_finance_is_public_and_the_nav_shows_it_to_everyone(): void
    {
        $this->liveLeague();
        $this->travelTo('2026-11-14 10:00');

        $this->get('/finance')->assertOk();
        $this->get('/finance/weeks/1')->assertOk();
        $this->get('/')->assertSee('aria-label="Main"', false)->assertSee('Finance')->assertDontSee('>Admin<', false);
    }

    public function test_recording_subs_is_admin_only(): void
    {
        $this->liveLeague();
        $this->post('/admin/subs', ['player_id' => 1, 'amount' => '2', 'paid_on' => '2026-11-14'])->assertRedirect('/admin/login');
        $this->delete('/admin/subs/1')->assertRedirect('/admin/login');
    }

    public function test_login_uses_a_username(): void
    {
        $admin = $this->admin();

        $this->post('/admin/login', ['username' => 'admin', 'password' => 'wrong'])->assertSessionHasErrors('username');
        $this->post('/admin/login', ['username' => 'admin', 'password' => 'password'])->assertRedirect('/admin');
        $this->assertAuthenticatedAs($admin);
    }

    public function test_edit_buttons_and_the_admin_link_only_show_when_logged_in(): void
    {
        $season = $this->liveLeague();
        $this->travelTo('2026-11-14 10:00');
        [$f, $a, $b] = $this->firstMatch($this->night($season, 1));
        $this->submitLines($this->night($season, 1), "{$a} beat {$b} 121");
        auth()->logout();

        $this->get('/')->assertOk()->assertDontSee('>Edit<', false)->assertDontSee('>Admin<', false)->assertSee('Week 2');

        $this->actingAs($this->admin())->get('/')->assertSee('>Edit<', false)->assertSee('>Admin<', false);
    }
}
