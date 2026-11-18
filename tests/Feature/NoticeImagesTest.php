<?php

namespace Tests\Feature;

use App\Models\Notice;
use App\Models\NoticeImage;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\Support\BuildsLeague;
use Tests\TestCase;

class NoticeImagesTest extends TestCase
{
    use BuildsLeague, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('public');
        $this->actingAs($this->admin());
    }

    private function fields(array $more = []): array
    {
        return ['title' => 'Photos', 'date' => '2026-11-08', 'content' => '<p>Some pictures</p>'] + $more;
    }

    private function photo(string $name = 'p.jpg', int $w = 800, int $h = 600): UploadedFile
    {
        return UploadedFile::fake()->image($name, $w, $h);
    }

    private function dimensions(string $path): array
    {
        $info = getimagesizefromstring(Storage::disk('public')->get($path));

        return [$info[0], $info[1], $info['mime']];
    }

    public function test_admin_pages_need_a_login_and_the_form_can_send_files(): void
    {
        auth()->logout();
        $this->post(route('admin.notices.store'), $this->fields())->assertRedirect('/admin/login');

        $this->actingAs($this->admin());
        $this->get(route('admin.notices.create'))->assertOk()
            ->assertSee('enctype="multipart/form-data"', false)
            ->assertSee('name="images[]"', false)
            ->assertSee('multiple', false);
    }

    public function test_one_picture_is_saved_as_a_resized_jpeg_and_a_thumbnail(): void
    {
        $this->post(route('admin.notices.store'), $this->fields(['images' => [$this->photo('big.png', 4000, 3000)]]))
            ->assertRedirect(route('admin.notices.index'));

        $image = NoticeImage::firstOrFail();
        [$w, $h, $mime] = $this->dimensions($image->path);
        $this->assertSame('image/jpeg', $mime);
        $this->assertSame([1600, 1200], [$w, $h]);
        $this->assertSame([1600, 1200], [$image->width, $image->height]);

        [$tw, $th] = $this->dimensions($image->thumb_path);
        $this->assertSame([640, 480], [$tw, $th]);
    }

    public function test_the_original_upload_is_never_kept(): void
    {
        $this->post(route('admin.notices.store'), $this->fields(['images' => [$this->photo('IMG_0001.png', 3000, 2000), $this->photo('IMG_0002.gif', 500, 500)]]));

        $files = Storage::disk('public')->allFiles();
        $this->assertCount(4, $files, 'two pictures: a large one and a thumbnail each, nothing else');
        foreach ($files as $file) {
            $this->assertStringEndsWith('.jpg', $file);
            $this->assertStringNotContainsString('IMG_000', $file);
        }
    }

    public function test_small_pictures_are_not_blown_up(): void
    {
        $this->post(route('admin.notices.store'), $this->fields(['images' => [$this->photo('s.jpg', 300, 200)]]));

        $image = NoticeImage::firstOrFail();
        $this->assertSame([300, 200], [$image->width, $image->height]);
        $this->assertSame([300, 200], array_slice($this->dimensions($image->thumb_path), 0, 2));
    }

    public function test_a_tall_picture_is_limited_on_its_longest_side(): void
    {
        $this->post(route('admin.notices.store'), $this->fields(['images' => [$this->photo('t.jpg', 2000, 4000)]]));

        $this->assertSame([800, 1600], [NoticeImage::first()->width, NoticeImage::first()->height]);
    }

    public function test_several_pictures_keep_the_order_they_were_chosen_in(): void
    {
        $this->post(route('admin.notices.store'), $this->fields(['images' => [$this->photo('a.jpg'), $this->photo('b.jpg'), $this->photo('c.jpg')]]));

        $notice = Notice::firstOrFail();
        $this->assertSame([1, 2, 3], $notice->images->pluck('position')->all());
        $this->assertCount(6, Storage::disk('public')->allFiles());
    }

    public function test_things_that_are_not_pictures_are_refused_and_nothing_is_saved(): void
    {
        $svg = UploadedFile::fake()->createWithContent('x.svg', '<svg xmlns="http://www.w3.org/2000/svg"><script>alert(1)</script></svg>');
        $fake = UploadedFile::fake()->createWithContent('photo.jpg', 'this is just text');
        $pdf = UploadedFile::fake()->create('doc.pdf', 20, 'application/pdf');

        foreach ([$svg, $fake, $pdf] as $file) {
            $this->post(route('admin.notices.store'), $this->fields(['images' => [$file]]))->assertSessionHasErrors();
        }
        $this->assertSame(0, Notice::count());
        $this->assertSame(0, NoticeImage::count());
        $this->assertSame([], Storage::disk('public')->allFiles());
    }

    public function test_one_bad_file_stops_the_whole_save(): void
    {
        $this->post(route('admin.notices.store'), $this->fields(['images' => [$this->photo(), UploadedFile::fake()->createWithContent('bad.jpg', 'nope')]]))
            ->assertSessionHasErrors();

        $this->assertSame(0, Notice::count());
        $this->assertSame([], Storage::disk('public')->allFiles());
    }

    public function test_there_is_a_limit_on_how_many_pictures_one_notice_can_have(): void
    {
        $max = config('league.images_per_notice');
        $many = collect(range(1, $max + 1))->map(fn ($i) => $this->photo("{$i}.jpg", 40, 30))->all();

        $this->post(route('admin.notices.store'), $this->fields(['images' => $many]))->assertSessionHasErrors('images');
        $this->assertSame(0, Notice::count());

        $ok = collect(range(1, $max))->map(fn ($i) => $this->photo("{$i}.jpg", 40, 30))->all();
        $this->post(route('admin.notices.store'), $this->fields(['images' => $ok]))->assertSessionHasNoErrors();
        $this->assertSame($max, NoticeImage::count());
    }

    public function test_the_limit_counts_pictures_already_there_but_not_ones_being_removed(): void
    {
        $max = config('league.images_per_notice');
        $notice = Notice::create(['title' => 'N', 'date' => '2026-11-08', 'content' => '<p>x</p>']);
        $files = collect(range(1, $max))->map(fn ($i) => $this->photo("{$i}.jpg", 40, 30))->all();
        $this->put(route('admin.notices.update', $notice), $this->fields(['images' => $files]));

        $this->put(route('admin.notices.update', $notice), $this->fields(['images' => [$this->photo()]]))->assertSessionHasErrors('images');
        $this->assertSame($max, $notice->images()->count());

        $first = $notice->images()->first();
        $this->put(route('admin.notices.update', $notice), $this->fields(['remove' => [$first->id], 'images' => [$this->photo()]]))->assertSessionHasNoErrors();
        $this->assertSame($max, $notice->images()->count());
        $this->assertNull(NoticeImage::find($first->id));
    }

    public function test_captions_can_be_changed_and_cleared(): void
    {
        $notice = Notice::create(['title' => 'N', 'date' => '2026-11-08', 'content' => '<p>x</p>']);
        $this->put(route('admin.notices.update', $notice), $this->fields(['images' => [$this->photo(), $this->photo('b.jpg')]]));
        [$a, $b] = $notice->images()->get()->all();

        $this->put(route('admin.notices.update', $notice), $this->fields(['captions' => [$a->id => '  The winners  ', $b->id => '   ']]));

        $this->assertSame('The winners', $a->fresh()->caption);
        $this->assertNull($b->fresh()->caption);
        $this->get(route('admin.notices.edit', $notice))->assertSee('The winners');
    }

    public function test_pictures_belonging_to_another_notice_cannot_be_changed(): void
    {
        $mine = Notice::create(['title' => 'Mine', 'date' => '2026-11-08', 'content' => '<p>x</p>']);
        $other = Notice::create(['title' => 'Other', 'date' => '2026-11-08', 'content' => '<p>x</p>']);
        $this->put(route('admin.notices.update', $other), $this->fields(['images' => [$this->photo()]]));
        $theirs = $other->images()->first();

        $this->put(route('admin.notices.update', $mine), $this->fields(['remove' => [$theirs->id], 'captions' => [$theirs->id => 'Hijacked']]));

        $this->assertNotNull($theirs->fresh());
        $this->assertNull($theirs->fresh()->caption);
        Storage::disk('public')->assertExists($theirs->path);
    }

    public function test_removing_a_picture_deletes_its_files(): void
    {
        $notice = Notice::create(['title' => 'N', 'date' => '2026-11-08', 'content' => '<p>x</p>']);
        $this->put(route('admin.notices.update', $notice), $this->fields(['images' => [$this->photo(), $this->photo('b.jpg')]]));
        [$a, $b] = $notice->images()->get()->all();

        $this->put(route('admin.notices.update', $notice), $this->fields(['remove' => [$a->id]]));

        Storage::disk('public')->assertMissing([$a->path, $a->thumb_path]);
        Storage::disk('public')->assertExists([$b->path, $b->thumb_path]);
        $this->assertSame(1, $notice->images()->count());
    }

    public function test_deleting_a_notice_deletes_all_its_picture_files(): void
    {
        $notice = Notice::create(['title' => 'N', 'date' => '2026-11-08', 'content' => '<p>x</p>']);
        $this->put(route('admin.notices.update', $notice), $this->fields(['images' => [$this->photo(), $this->photo('b.jpg')]]));
        $this->assertCount(4, Storage::disk('public')->allFiles());

        $this->delete(route('admin.notices.destroy', $notice))->assertRedirect(route('admin.notices.index'));

        $this->assertSame([], Storage::disk('public')->allFiles());
        $this->assertSame(0, NoticeImage::count());
    }

    public function test_the_admin_list_says_how_many_pictures_a_notice_has(): void
    {
        $notice = Notice::create(['title' => 'N', 'date' => '2026-11-08', 'content' => '<p>x</p>']);
        $this->get(route('admin.notices.index'))->assertDontSee('picture');

        $this->put(route('admin.notices.update', $notice), $this->fields(['images' => [$this->photo()]]));
        $this->get(route('admin.notices.index'))->assertSee('1 picture');

        $this->put(route('admin.notices.update', $notice), $this->fields(['images' => [$this->photo(), $this->photo('b.jpg')]]));
        $this->get(route('admin.notices.index'))->assertSee('3 pictures');
    }

    public function test_the_public_page_shows_one_picture_large(): void
    {
        $this->post(route('admin.notices.store'), $this->fields(['images' => [$this->photo('one.jpg', 1200, 800)]]));
        $image = NoticeImage::first();
        $image->update(['caption' => 'Our <b>winners</b>']);
        auth()->logout();

        $this->get('/notices')->assertOk()
            ->assertSee('class="notice-photo"', false)
            ->assertDontSee('class="gallery"', false)
            ->assertSee($image->url(), false)
            ->assertSee('width="1200" height="800"', false)
            ->assertSee('Our &lt;b&gt;winners&lt;/b&gt;', false)
            ->assertDontSee('Our <b>winners</b>', false);
    }

    public function test_the_public_page_shows_several_pictures_as_a_gallery_of_thumbnails(): void
    {
        $this->post(route('admin.notices.store'), $this->fields(['images' => [$this->photo('a.jpg'), $this->photo('b.jpg'), $this->photo('c.jpg')]]));
        $notice = Notice::first();
        auth()->logout();

        $html = $this->get('/notices')->assertOk()->assertSee('class="gallery"', false)->assertDontSee('class="notice-photo"', false)->getContent();

        $this->assertSame(3, substr_count($html, 'data-gallery="notice-'.$notice->id.'"'));
        foreach ($notice->images as $image) {
            $this->assertStringContainsString('src="'.$image->thumbUrl().'"', $html);
            $this->assertStringContainsString('href="'.$image->url().'"', $html);
        }
        $this->assertStringContainsString('js/gallery.js', $html);
    }

    public function test_a_notice_without_pictures_is_unchanged(): void
    {
        $this->post(route('admin.notices.store'), $this->fields());
        auth()->logout();

        $this->get('/notices')->assertOk()->assertSee('Some pictures')->assertDontSee('notice-photo', false)->assertDontSee('class="gallery"', false);
    }

    public function test_picture_addresses_use_the_storage_folder_for_the_local_disk(): void
    {
        $image = new NoticeImage(['path' => 'notices/1/abc.jpg', 'thumb_path' => 'notices/1/abc-thumb.jpg']);

        $this->assertSame(asset('storage/notices/1/abc.jpg'), $image->url());
        $this->assertSame(asset('storage/notices/1/abc-thumb.jpg'), $image->thumbUrl());
    }
}
