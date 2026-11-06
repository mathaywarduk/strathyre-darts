@use('App\Support\Money')
<x-layouts.admin title="Settings">
    <h1 class="h2" style="margin-top: 16px">Settings</h1>

    <form method="POST" action="{{ route('admin.settings.update') }}" class="card stack" data-save-shortcut>
        @csrf @method('PUT')
        <h2 class="h2">League rules</h2>
        @if (! $settings->isComplete())
            <p class="owed-text" style="margin: 0; font-weight: 600">Still to set: {{ implode(', ', $settings->missing()) }}. The league can’t go live until all three are set.</p>
        @endif
        <div class="cols">
            <div>
                <label class="label" for="win_points">Points for a win</label>
                <input class="field" id="win_points" name="win_points" type="number" min="0" max="99" inputmode="numeric" value="{{ old('win_points', $settings->win_points) }}" required>
            </div>
            <div>
                <label class="label" for="loss_points">Points for a loss</label>
                <input class="field" id="loss_points" name="loss_points" type="number" min="0" max="99" inputmode="numeric" value="{{ old('loss_points', $settings->loss_points) }}" required>
            </div>
        </div>
        <div>
            <label class="label" for="subs">Subs per player per league night (£)</label>
            <input class="field" id="subs" name="subs" inputmode="decimal" value="{{ old('subs', Money::input($settings->subs_pence)) }}" required>
            <p class="hint">Changing this re-prices the whole season: every week, paid or owed, uses the new amount.</p>
        </div>
        <button class="btn" type="submit">Save settings</button>
    </form>

    <form method="POST" action="{{ route('admin.settings.password') }}" class="card stack">
        @csrf @method('PUT')
        <h2 class="h2">Change password</h2>
        <p class="sub" style="margin: 0">The username is always “admin”.</p>
        <div>
            <label class="label" for="current_password">Current password</label>
            <input class="field" id="current_password" name="current_password" type="password" required autocomplete="current-password">
        </div>
        <div>
            <label class="label" for="password">New password</label>
            <input class="field" id="password" name="password" type="password" required minlength="{{ config('league.password_min') }}" autocomplete="new-password">
            <p class="hint">At least {{ config('league.password_min') }} characters.</p>
        </div>
        <div>
            <label class="label" for="password_confirmation">New password again</label>
            <input class="field" id="password_confirmation" name="password_confirmation" type="password" required autocomplete="new-password">
        </div>
        <button class="btn" type="submit">Change password</button>
    </form>
</x-layouts.admin>
