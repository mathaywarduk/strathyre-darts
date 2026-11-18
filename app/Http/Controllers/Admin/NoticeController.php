<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Notice;
use App\Services\ImageException;
use App\Services\NoticeImages;
use App\Support\RichText;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\View\View;

class NoticeController extends Controller
{
    public function __construct(private NoticeImages $images) {}

    public function index(): View
    {
        return view('admin.notices.index', ['notices' => Notice::withCount('images')->latestFirst()->get()]);
    }

    public function create(): View
    {
        return view('admin.notices.form', ['notice' => new Notice(['date' => today()])]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validated($request);
        $pictures = $this->prepare($request, 0);

        $notice = Notice::create($data);
        foreach ($pictures as $picture) {
            $this->images->attach($notice, $picture);
        }

        return redirect()->route('admin.notices.index')->with('status', 'Notice added.');
    }

    public function edit(Notice $notice): View
    {
        return view('admin.notices.form', ['notice' => $notice->load('images')]);
    }

    public function update(Request $request, Notice $notice): RedirectResponse
    {
        $data = $this->validated($request);
        $existing = $notice->images()->get();
        $remove = array_map('intval', (array) $request->input('remove', []));
        $kept = $existing->reject(fn ($i) => in_array($i->id, $remove, true));

        $pictures = $this->prepare($request, $kept->count());

        $notice->update($data);
        foreach ($existing->whereIn('id', $remove) as $image) {
            $image->delete(); // and its files
        }
        foreach ((array) $request->input('captions', []) as $id => $caption) {
            $image = $kept->firstWhere('id', (int) $id);
            if ($image) {
                $image->update(['caption' => trim((string) $caption) !== '' ? trim((string) $caption) : null]);
            }
        }
        foreach ($pictures as $picture) {
            $this->images->attach($notice, $picture);
        }

        return redirect()->route('admin.notices.index')->with('status', 'Saved.');
    }

    public function destroy(Notice $notice): RedirectResponse
    {
        $notice->delete();

        return redirect()->route('admin.notices.index')->with('status', 'Notice deleted.');
    }

    private function validated(Request $request): array
    {
        $max = (int) config('league.images_per_notice');
        $kb = (int) config('league.image_max_kb');
        $limit = ini_get('upload_max_filesize');

        $data = $request->validate([
            'title' => ['required', 'string', 'max:150'],
            'date' => ['required', 'date_format:Y-m-d'],
            'expires_on' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:date'],
            'content' => ['required', 'string', 'max:100000'],
            'images' => ['nullable', 'array', "max:{$max}"],
            'images.*' => ['file', 'mimetypes:image/jpeg,image/png,image/webp,image/gif', "max:{$kb}"],
            'captions' => ['nullable', 'array'],
            'captions.*' => ['nullable', 'string', 'max:200'],
            'remove' => ['nullable', 'array'],
            'remove.*' => ['integer'],
        ], [
            'content.required' => 'Add some text to the notice.',
            'expires_on.after_or_equal' => 'The expiry date can’t be before the notice’s own date.',
            'images.max' => "A notice can have up to {$max} pictures.",
            'images.*.uploaded' => "A picture was too big for the server to accept (its limit is {$limit}). Try a smaller one.",
            'images.*.mimetypes' => 'Pictures need to be JPG, PNG, WebP or GIF.',
            'images.*.max' => 'A picture is bigger than '.round($kb / 1024).' MB. Try a smaller one.',
            'images.*.file' => 'One of the pictures didn’t upload properly. Try again.',
            'captions.*.max' => 'A caption can be up to 200 characters.',
        ]);

        $data['title'] = trim($data['title']);
        $data['expires_on'] = $data['expires_on'] ?? null;
        $data['content'] = RichText::clean($data['content']);
        if ($data['content'] === null) {
            back()->withInput()->withErrors(['content' => 'Add some text to the notice.'])->throwResponse();
        }

        return collect($data)->only(['title', 'date', 'expires_on', 'content'])->all();
    }

    /**
     * Open and shrink the uploaded pictures before anything is saved, so a bad one stops the whole save.
     *
     * @return list<array{full: string, thumb: string, width: int, height: int}>
     */
    private function prepare(Request $request, int $already): array
    {
        $files = array_values(array_filter((array) $request->file('images', []), fn ($f) => $f instanceof UploadedFile));
        $max = (int) config('league.images_per_notice');

        if ($already + count($files) > $max) {
            back()->withInput()->withErrors(['images' => "A notice can have up to {$max} pictures. It has {$already} already."])->throwResponse();
        }

        try {
            return array_map(fn (UploadedFile $file) => $this->images->prepare($file), $files);
        } catch (ImageException $e) {
            back()->withInput()->withErrors(['images' => $e->getMessage()])->throwResponse();
        }
    }
}
