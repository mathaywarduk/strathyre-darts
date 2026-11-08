<?php

namespace App\Http\Controllers;

use App\Models\Setting;
use App\Support\RichText;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class RulesController extends Controller
{
    public function show(): View|RedirectResponse
    {
        $rules = Setting::query()->value('rules_html');
        if (! RichText::hasContent($rules)) {
            return redirect()->route('home');
        }

        return view('rules', ['rules' => $rules]);
    }
}
