<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Setting;
use App\Support\RichText;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/** The league's written rules: one page of formatted text. */
class RulesController extends Controller
{
    public function edit(): View
    {
        return view('admin.rules', ['rules' => Setting::current()->rules_html]);
    }

    public function update(Request $request): RedirectResponse
    {
        $data = $request->validate(['content' => ['nullable', 'string', 'max:100000']]);
        $clean = RichText::clean($data['content'] ?? null);

        Setting::current()->update(['rules_html' => $clean]);

        return redirect()->route('admin.rules.edit')->with('status', $clean
            ? 'Rules saved.'
            : 'Rules cleared. The Rules page is hidden until you add some.');
    }
}
