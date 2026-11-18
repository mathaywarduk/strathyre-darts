<?php

namespace App\Http\Controllers;

use App\Models\Notice;
use App\Support\LeagueState;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class NoticesController extends Controller
{
    public function index(): View|RedirectResponse
    {
        if (! LeagueState::noticesVisible()) {
            return redirect()->route('home');
        }

        $notices = Notice::active()->with('images')->latestFirst()->get();
        if ($notices->isEmpty()) {
            return redirect()->route('home');
        }

        return view('notices', ['notices' => $notices]);
    }
}
