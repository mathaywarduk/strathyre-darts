<?php

namespace App\Http\Controllers;

use App\Models\Notice;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class NoticesController extends Controller
{
    public function index(): View|RedirectResponse
    {
        $notices = Notice::active()->latestFirst()->get();
        if ($notices->isEmpty()) {
            return redirect()->route('home');
        }

        return view('notices', ['notices' => $notices]);
    }
}
