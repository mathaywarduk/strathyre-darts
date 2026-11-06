<?php

namespace App\Http\Controllers;

use App\Models\Season;
use Illuminate\View\View;

class SeasonArchiveController extends Controller
{
    public function index(): View
    {
        return view('seasons.index', [
            'seasons' => Season::where('status', 'archived')->orderByDesc('starts_on')->get(),
        ]);
    }

    public function show(Season $season): View
    {
        abort_unless($season->isArchived() && $season->snapshot, 404);

        return view('seasons.show', ['season' => $season, 'snapshot' => $season->snapshot]);
    }
}
