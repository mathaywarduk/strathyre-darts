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

    public function test_finance_does_not_exist_for_guests(): void
    {
        $this->liveLeague();
        $this->get('/finance')->assertNotFound();
        $this->get('/finance/weeks/1')->assertNotFound();
        $this->post('/finance/weeks/1/players/1')->assertNotFound();
    }

    public function test_login_uses_a_username(): void
    {
        $admin = $this->admin();

        $this->post('/admin/login', ['username' => 'admin', 'password' => 'wrong'])->assertSessionHasErrors('username');
        $this->post('/admin/login', ['username' => 'admin', 'password' => 'password'])->assertRedirect('/admin');
        $this->assertAuthenticatedAs($admin);
    }

    public function test_finance_nav_and_edit_buttons_only_show_when_logged_in(): void
    {
        $season = $this->liveLeague();
        $this->travelTo('2026-11-14 10:00');
        [$f, $a, $b] = $this->firstMatch($this->night($season, 1));
        $this->submitLines($this->night($season, 1), "{$a} beat {$b} 15");
        auth()->logout();

        $this->get('/')->assertOk()->assertDontSee('Finance')->assertDontSee('>Edit<', false)->assertSee('Week 2');

        $this->actingAs($this->admin())->get('/')->assertSee('Finance')->assertSee('>Edit<', false);
        $this->get('/finance')->assertOk()->assertSee('Subs received');
    }
}
