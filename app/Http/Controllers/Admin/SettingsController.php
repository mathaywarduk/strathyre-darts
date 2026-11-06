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
            'win_points' => ['required', 'integer', 'between:0,99'],
            'loss_points' => ['required', 'integer', 'between:0,99'],
            'subs' => ['required', 'regex:/^£?\d+(\.\d{1,2})?$/u'],
        ], ['subs.regex' => 'Enter the subs as an amount, like 2 or 2.50.']);

        Setting::current()->update([
            'win_points' => $data['win_points'],
            'loss_points' => $data['loss_points'],
            'subs_pence' => Money::parse($data['subs']),
        ]);

        return redirect()->route('admin.settings.edit')->with('status', 'Settings saved.');
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
