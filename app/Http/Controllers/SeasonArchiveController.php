<?php

namespace App\Http\Controllers;

use App\Models\Season;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class SeasonArchiveController extends Controller
{
    public function index(): View
    {
        return view('seasons.index', [
            'seasons' => Season::where('status', 'archived')->orderByDesc('starts_on')->get(),
        ]);
    }

    public function show(Season $season): View|RedirectResponse
    {
        if (! $season->isArchived() || ! $season->snapshot) {
            return redirect()->route('seasons.index');
        }

        return view('seasons.show', ['season' => $season, 'snapshot' => $season->snapshot]);
    }
}
