<?php

namespace Tests\Feature;

use App\Models\Notice;
use App\Models\Setting;
use App\Support\RichText;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\BuildsLeague;
use Tests\TestCase;

class RulesAndNoticesTest extends TestCase
{
    use BuildsLeague, RefreshDatabase;

    /* ---- the cleaner: only bold, italic, underline, links, lists and a line get through ---- */

    public function test_formatting_that_is_allowed_is_kept(): void
    {
        $html = '<p>Hi <strong>there</strong> <em>you</em> <u>all</u></p><ul><li>a</li></ul><ol><li>b</li></ol><hr>';
        $clean = RichText::clean($html);

        foreach (['<strong>there</strong>', '<em>you</em>', '<u>all</u>', '<ul><li>a</li></ul>', '<ol><li>b</li></ol>', '<hr'] as $keep) {
            $this->assertStringContainsString($keep, $clean);
        }
    }

    public function test_everything_else_is_removed(): void
    {
        $clean = RichText::clean('<p onclick="x()" style="color:red">Hi</p><script>alert(1)</script><img src="x" onerror="alert(1)"><iframe src="y"></iframe><span class="z">span</span><h1>Big</h1><table><tr><td>cell</td></tr></table>');

        foreach (['script', 'alert', 'onclick', 'onerror', 'style', '<img', 'iframe', 'class', '<table', '<h1', '<span'] as $bad) {
            $this->assertStringNotContainsString($bad, $clean, "{$bad} should be gone");
        }
        $this->assertStringContainsString('Hi', $clean);
    }

    public function test_links_are_http_https_or_mailto_and_open_safely(): void
    {
        $clean = RichText::clean('<a href="javascript:alert(1)">bad</a> <a href="data:text/html,x">data</a> <a href="https://example.com/x?a=1&b=2">good</a> <a href="mailto:a@b.co">mail</a>');

        $this->assertStringNotContainsString('javascript', $clean);
        $this->assertStringNotContainsString('data:', $clean);
        $this->assertStringContainsString('href="https://example.com/x?a=1&amp;b=2"', $clean);
        $this->assertStringContainsString('target="_blank"', $clean);
        $this->assertStringContainsString('noopener', $clean);
        $this->assertStringContainsString('href="mailto:a@b.co"', $clean);
    }

    public function test_lines_from_a_browser_stay_separate_paragraphs_and_empty_ones_go(): void
    {
        $this->assertSame('<p>one</p><p>two</p>', str_replace("\n", '', RichText::clean('<div>one</div><div>two</div>')));
        $this->assertSame('<p>Plain text</p>', RichText::clean('Plain text'));
        $this->assertNull(RichText::clean('<p></p><p> </p>'));
        $this->assertNull(RichText::clean('   '));
        $this->assertNull(RichText::clean(null));
        $this->assertFalse(RichText::hasContent('<p> </p>'));
        $this->assertTrue(RichText::hasContent('<p>x</p>'));
    }

    public function test_empty_lines_and_bullets_left_by_the_editor_are_dropped(): void
    {
        $clean = RichText::clean('<p>Keep</p><p><br></p><ul><li>one</li><li><b><br></b></li></ul><ol><li><br></li></ol><p><strong> </strong></p>');

        $this->assertStringContainsString('<li>one</li>', $clean);
        $this->assertSame(1, substr_count($clean, '<li>'), 'the empty bullets are gone');
        $this->assertStringNotContainsString('<ol>', $clean, 'a list with nothing in it is gone');
        $this->assertSame(1, substr_count($clean, '<p>'), 'only the real paragraph is left');
        $this->assertNull(RichText::clean('<p><br></p><ul><li><br></li></ul>'));
    }

    /* ---- access ---- */

    public function test_the_editing_screens_need_a_login(): void
    {
        $this->get('/admin/rules')->assertRedirect('/admin/login');
        $this->put('/admin/rules', ['content' => 'x'])->assertRedirect('/admin/login');
        $this->get('/admin/notices')->assertRedirect('/admin/login');
        $this->get('/admin/notices/create')->assertRedirect('/admin/login');
        $this->post('/admin/notices', [])->assertRedirect('/admin/login');
        $this->delete('/admin/notices/1')->assertRedirect('/admin/login');
    }

    public function test_the_admin_menu_links_to_both(): void
    {
        $this->actingAs($this->admin());
        $this->get(route('admin.dashboard'))->assertSee('>Notices</a>', false)->assertSee('>Rules</a>', false);
    }

    /* ---- rules ---- */

    public function test_the_rules_editor_offers_exactly_the_allowed_formatting(): void
    {
        $this->actingAs($this->admin());
        $html = $this->get(route('admin.rules.edit'))->assertOk()->getContent();

        foreach (['bold', 'italic', 'underline', 'link', 'insertUnorderedList', 'insertOrderedList', 'insertHorizontalRule'] as $cmd) {
            $this->assertStringContainsString('data-cmd="'.$cmd.'"', $html);
        }
        $this->assertSame(7, substr_count($html, 'data-cmd='), 'no images, headings or anything else');
        $this->assertStringContainsString('js/editor.js', $html);
    }

    public function test_saving_rules_stores_clean_html_and_shows_the_page(): void
    {
        $this->actingAs($this->admin());
        $this->put(route('admin.rules.update'), ['content' => '<p>No <strong>cheating</strong>.</p><script>alert(1)</script><img src=x onerror=alert(1)>'])
            ->assertRedirect(route('admin.rules.edit'))->assertSessionHas('status', 'Rules saved.');

        $stored = Setting::current()->rules_html;
        $this->assertStringContainsString('<strong>cheating</strong>', $stored);
        $this->assertStringNotContainsString('script', $stored);
        $this->assertStringNotContainsString('onerror', $stored);

        auth()->logout();
        $this->get('/rules')->assertOk()->assertSee('Rules')->assertSee('<strong>cheating</strong>', false)->assertDontSee('alert(1)', false);
    }

    public function test_the_rules_tab_only_shows_once_there_are_rules(): void
    {
        $this->get('/')->assertDontSee(route('rules'));
        $this->get('/rules')->assertRedirect(route('home'));

        Setting::current()->update(['rules_html' => '<p>Be nice.</p>']);
        $this->get('/')->assertSee('href="'.route('rules').'"', false);
        $this->get('/rules')->assertSee('aria-current="page" >Rules</a>', false);

        $this->actingAs($this->admin());
        $this->put(route('admin.rules.update'), ['content' => '<p> </p>'])->assertSessionHas('status', fn ($m) => str_contains($m, 'cleared'));
        $this->assertNull(Setting::current()->rules_html);
        auth()->logout();
        $this->get('/')->assertDontSee(route('rules'));
    }

    public function test_the_rules_are_shown_in_the_editor_when_editing_again(): void
    {
        Setting::current()->update(['rules_html' => '<p>Be <em>nice</em>.</p>']);
        $this->actingAs($this->admin());

        $this->get(route('admin.rules.edit'))->assertSee('&lt;p&gt;Be &lt;em&gt;nice&lt;/em&gt;.&lt;/p&gt;', false);
    }

    public function test_the_rules_do_not_need_the_league_to_be_live(): void
    {
        Setting::current()->update(['rules_html' => '<p>Rules before the season.</p>']);

        $this->get('/')->assertSee('Coming soon')->assertSee(route('rules'));
        $this->get('/rules')->assertOk()->assertSee('Rules before the season.');
    }

    /* ---- notices ---- */

    public function test_a_notice_has_a_title_a_date_and_formatted_content(): void
    {
        $this->actingAs($this->admin());
        $this->post(route('admin.notices.store'), ['title' => '  Christmas party  ', 'date' => '2026-11-08', 'content' => '<p>Come <strong>along</strong>!</p>'])
            ->assertRedirect(route('admin.notices.index'));

        $n = Notice::first();
        $this->assertSame('Christmas party', $n->title);
        $this->assertSame('2026-11-08', $n->date->toDateString());
        $this->assertStringContainsString('<strong>along</strong>', $n->content);

        $this->get(route('admin.notices.index'))->assertSee('Christmas party')->assertSee('8 Nov 2026');
    }

    public function test_a_notice_needs_a_title_a_real_date_and_some_text(): void
    {
        $this->actingAs($this->admin());

        $this->post(route('admin.notices.store'), ['title' => '', 'date' => '2026-11-08', 'content' => '<p>x</p>'])->assertSessionHasErrors('title');
        $this->post(route('admin.notices.store'), ['title' => 'T', 'date' => 'soon', 'content' => '<p>x</p>'])->assertSessionHasErrors('date');
        $this->post(route('admin.notices.store'), ['title' => 'T', 'date' => '2026-11-08', 'content' => ''])->assertSessionHasErrors('content');
        $this->post(route('admin.notices.store'), ['title' => 'T', 'date' => '2026-11-08', 'content' => '<p> </p><script>x</script>'])->assertSessionHasErrors('content');
        $this->assertSame(0, Notice::count());
    }

    public function test_a_failed_save_does_not_put_unclean_html_back_in_the_editor(): void
    {
        $this->actingAs($this->admin());

        $page = $this->from(route('admin.notices.create'))
            ->post(route('admin.notices.store'), ['title' => '', 'date' => '2026-11-08', 'content' => '<p>Hello</p><img src=x onerror=alert(1)>'])
            ->assertRedirect(route('admin.notices.create'));
        $body = $this->get(route('admin.notices.create'))->getContent();

        $this->assertStringContainsString('Hello', $body);
        $this->assertStringNotContainsString('onerror', $body);
    }

    public function test_notices_are_listed_newest_first(): void
    {
        Notice::create(['title' => 'Older', 'date' => '2026-10-01', 'content' => '<p>old</p>']);
        Notice::create(['title' => 'Newest', 'date' => '2026-11-08', 'content' => '<p>new</p>']);
        Notice::create(['title' => 'Middle', 'date' => '2026-10-20', 'content' => '<p>mid</p>']);

        $html = $this->get('/notices')->assertOk()->assertSee('8th November 2026')->assertSee('1st October 2026')->getContent();
        $this->assertTrue(strpos($html, 'Newest') < strpos($html, 'Middle') && strpos($html, 'Middle') < strpos($html, 'Older'));
    }

    public function test_the_notices_tab_only_shows_when_there_is_a_notice(): void
    {
        $this->get('/')->assertDontSee(route('notices'));
        $this->get('/notices')->assertRedirect(route('home'));

        $this->actingAs($this->admin());
        $this->post(route('admin.notices.store'), ['title' => 'Hello', 'date' => '2026-11-08', 'content' => '<p>Welcome</p>']);
        auth()->logout();

        $this->get('/')->assertSee('href="'.route('notices').'"', false);
        $this->get('/notices')->assertSee('aria-current="page" >Notices</a>', false);

        $this->actingAs($this->admin());
        $this->delete(route('admin.notices.destroy', Notice::first()))->assertRedirect(route('admin.notices.index'));
        auth()->logout();
        $this->get('/')->assertDontSee(route('notices'));
    }

    public function test_a_notice_can_be_edited(): void
    {
        $this->actingAs($this->admin());
        $n = Notice::create(['title' => 'Old title', 'date' => '2026-11-01', 'content' => '<p>old</p>']);

        $this->get(route('admin.notices.edit', $n))->assertOk()->assertSee('Old title')->assertSee('value="2026-11-01"', false)->assertSee('Delete notice');
        $this->put(route('admin.notices.update', $n), ['title' => 'New title', 'date' => '2026-11-09', 'content' => '<p><u>new</u></p>'])->assertRedirect(route('admin.notices.index'));

        $n->refresh();
        $this->assertSame(['New title', '2026-11-09'], [$n->title, $n->date->toDateString()]);
        $this->assertStringContainsString('<u>new</u>', $n->content);
    }

    public function test_notice_content_is_shown_as_formatted_text_and_titles_are_escaped(): void
    {
        $this->actingAs($this->admin());
        $this->post(route('admin.notices.store'), ['title' => '<b>Big</b> news', 'date' => '2026-11-08', 'content' => '<ul><li>One</li><li>Two</li></ul><hr><p><a href="https://example.com">link</a></p>']);
        auth()->logout();

        $this->get('/notices')->assertSee('&lt;b&gt;Big&lt;/b&gt; news', false)
            ->assertSee('<ul><li>One</li><li>Two</li></ul>', false)->assertSee('<hr', false)->assertSee('href="https://example.com"', false);
    }

    public function test_the_notice_form_starts_with_todays_date(): void
    {
        $this->travelTo('2026-11-08 10:00');
        $this->actingAs($this->admin());

        $this->get(route('admin.notices.create'))->assertSee('value="2026-11-08"', false);
    }

    /* ---- expiry ---- */

    public function test_a_notice_with_an_expiry_is_shown_up_to_and_including_that_day(): void
    {
        Notice::create(['title' => 'Quiz night', 'date' => '2026-11-01', 'content' => '<p>Bring a pen</p>', 'expires_on' => '2026-11-20']);

        $this->travelTo('2026-11-19 09:00');
        $this->get('/notices')->assertOk()->assertSee('Quiz night');
        $this->travelTo('2026-11-20 23:30'); // the last day
        $this->get('/notices')->assertOk()->assertSee('Quiz night');
        $this->get('/')->assertSee(route('notices'));

        $this->travelTo('2026-11-21 00:30'); // gone the next day
        $this->get('/notices')->assertRedirect(route('home'));
        $this->get('/')->assertDontSee(route('notices'));
    }

    public function test_notices_without_an_expiry_stay_up_and_expired_ones_drop_out_of_the_list(): void
    {
        Notice::create(['title' => 'Always here', 'date' => '2026-10-01', 'content' => '<p>x</p>']);
        Notice::create(['title' => 'Old news', 'date' => '2026-10-02', 'content' => '<p>y</p>', 'expires_on' => '2026-10-10']);
        Notice::create(['title' => 'Current', 'date' => '2026-11-01', 'content' => '<p>z</p>', 'expires_on' => '2026-12-31']);
        $this->travelTo('2026-11-08 10:00');

        $this->get('/notices')->assertSee('Always here')->assertSee('Current')->assertDontSee('Old news');
    }

    public function test_the_admin_sees_expired_notices_marked_as_expired(): void
    {
        Notice::create(['title' => 'Old news', 'date' => '2026-10-02', 'content' => '<p>y</p>', 'expires_on' => '2026-10-10']);
        Notice::create(['title' => 'Current', 'date' => '2026-11-01', 'content' => '<p>z</p>', 'expires_on' => '2026-12-31']);
        $this->travelTo('2026-11-08 10:00');
        $this->actingAs($this->admin());

        $html = $this->get(route('admin.notices.index'))->assertSee('Old news')->assertSee('Current')->assertSee('until 31 Dec')->getContent();
        $this->assertSame(1, substr_count($html, '>Expired<'));
    }

    public function test_the_expiry_is_optional_but_cannot_be_before_the_notice_date(): void
    {
        $this->actingAs($this->admin());
        $base = ['title' => 'T', 'date' => '2026-11-08', 'content' => '<p>x</p>'];

        $this->post(route('admin.notices.store'), $base)->assertSessionHasNoErrors();
        $this->assertNull(Notice::first()->expires_on);

        $this->post(route('admin.notices.store'), $base + ['expires_on' => '2026-11-01'])->assertSessionHasErrors('expires_on');
        $this->post(route('admin.notices.store'), $base + ['expires_on' => 'whenever'])->assertSessionHasErrors('expires_on');
        $this->post(route('admin.notices.store'), $base + ['expires_on' => '2026-11-08'])->assertSessionHasNoErrors(); // same day is fine
        $this->assertSame(2, Notice::count());
    }

    public function test_an_expiry_can_be_added_and_removed_when_editing(): void
    {
        $this->actingAs($this->admin());
        $n = Notice::create(['title' => 'T', 'date' => '2026-11-08', 'content' => '<p>x</p>']);

        $this->get(route('admin.notices.edit', $n))->assertSee('Hide after (optional)')->assertSee('name="expires_on"', false);
        $this->put(route('admin.notices.update', $n), ['title' => 'T', 'date' => '2026-11-08', 'expires_on' => '2026-12-01', 'content' => '<p>x</p>']);
        $this->assertSame('2026-12-01', $n->fresh()->expires_on->toDateString());

        $this->put(route('admin.notices.update', $n), ['title' => 'T', 'date' => '2026-11-08', 'expires_on' => '', 'content' => '<p>x</p>']);
        $this->assertNull($n->fresh()->expires_on);
    }

    /* ---- links look different from underlined text ---- */

    public function test_links_in_formatted_text_have_their_own_colour_unlike_plain_underlining(): void
    {
        $css = file_get_contents(public_path('css/app.css'));

        $this->assertMatchesRegularExpression('/\.rich a \{[^}]*color: var\(--link\)/', $css);
        $this->assertMatchesRegularExpression('/\.rich u \{[^}]*text-decoration-thickness/', $css);
        $this->assertDoesNotMatchRegularExpression('/\.rich u \{[^}]*color:/', $css, 'underlined text keeps the text colour');
        $this->assertStringContainsString('--link: #6cc4ff', $css);
    }
}
