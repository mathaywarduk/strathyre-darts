<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Setting;
use App\Support\Money;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class SettingsController extends Controller
{
    public function edit(): View
    {
        return view('admin.settings', ['settings' => Setting::current()]);
    }

    public function update(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'win_points' => ['nullable', 'integer', 'between:0,99'],
            'loss_points' => ['nullable', 'integer', 'between:0,99'],
            'subs' => ['nullable', 'regex:/^£?\d+(\.\d{1,2})?$/u'],
        ], ['subs.regex' => 'Enter the subs as an amount, like 2 or 2.50.']);

        $settings = Setting::current();
        $settings->update([
            'win_points' => $data['win_points'] ?? null,
            'loss_points' => $data['loss_points'] ?? null,
            'subs_pence' => Money::parse($data['subs'] ?? null),
        ]);

        return redirect()->route('admin.settings.edit')->with('status', $settings->isComplete()
            ? 'Settings saved.'
            : 'Settings saved. The public site shows “Coming soon” until points for a win and a loss and the subs amount are all set.');
    }

    public function password(Request $request): RedirectResponse
    {
        $min = config('league.password_min');
        $data = $request->validate([
            'current_password' => ['required', 'current_password'],
            'password' => ['required', "min:{$min}", 'confirmed'],
        ], ['current_password.current_password' => 'That isn’t the current password.']);

        $request->user()->update(['password' => $data['password']]);

        return redirect()->route('admin.settings.edit')->with('status', 'Password changed.');
    }
}
