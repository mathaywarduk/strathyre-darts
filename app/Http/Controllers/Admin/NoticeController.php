<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Notice;
use App\Support\RichText;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class NoticeController extends Controller
{
    public function index(): View
    {
        return view('admin.notices.index', ['notices' => Notice::latestFirst()->get()]);
    }

    public function create(): View
    {
        return view('admin.notices.form', ['notice' => new Notice(['date' => today()])]);
    }

    public function store(Request $request): RedirectResponse
    {
        Notice::create($this->validated($request));

        return redirect()->route('admin.notices.index')->with('status', 'Notice added.');
    }

    public function edit(Notice $notice): View
    {
        return view('admin.notices.form', ['notice' => $notice]);
    }

    public function update(Request $request, Notice $notice): RedirectResponse
    {
        $notice->update($this->validated($request));

        return redirect()->route('admin.notices.index')->with('status', 'Saved.');
    }

    public function destroy(Notice $notice): RedirectResponse
    {
        $notice->delete();

        return redirect()->route('admin.notices.index')->with('status', 'Notice deleted.');
    }

    private function validated(Request $request): array
    {
        $data = $request->validate([
            'title' => ['required', 'string', 'max:150'],
            'date' => ['required', 'date_format:Y-m-d'],
            'expires_on' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:date'],
            'content' => ['required', 'string', 'max:100000'],
        ], [
            'content.required' => 'Add some text to the notice.',
            'expires_on.after_or_equal' => 'The expiry date can’t be before the notice’s own date.',
        ]);

        $data['title'] = trim($data['title']);
        $data['expires_on'] = $data['expires_on'] ?? null;
        $data['content'] = RichText::clean($data['content']);
        if ($data['content'] === null) {
            back()->withInput()->withErrors(['content' => 'Add some text to the notice.'])->throwResponse();
        }

        return $data;
    }
}
