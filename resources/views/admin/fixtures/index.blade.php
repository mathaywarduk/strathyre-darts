@use('App\Support\Dates')
@use('App\Services\SeasonCalendar')
@php
    $startOn = $form['start_on'];
    $offChecked = collect($form['off'])->all();
    $fridays = SeasonCalendar::weekly(\Carbon\CarbonImmutable::parse($startOn), 30);
@endphp
<x-layouts.admin title="Fixtures">
    <h1 class="h2" style="margin-top: 16px">Fixtures</h1>
    <p class="sub">Season {{ $season->label }}. Every player meets every other player once.</p>

    @if ($league->hasFixtures())
        <div class="card">
            <h2 class="h2">Saved fixtures</h2>
            <p class="sub">{{ $league->totalWeeks() }} weeks, {{ $league->fixtures->count() }} matches. Move a week to a different night if it’s being played on another day: subs are due from the new date.</p>
            @foreach ($league->nights as $night)
                <form method="POST" action="{{ route('admin.fixtures.night', $night) }}" class="line wrap">
                    @csrf @method('PUT')
                    <span>
                        <strong>Week {{ $night->week }}</strong>
                        <span class="mute">{{ $league->fixturesForNight($night)->count() }} matches @if ($night->isSubmitted())· results in @endif</span>
                    </span>
                    <span class="right">
                        <input class="field date-sm" type="date" name="date" value="{{ $night->date->toDateString() }}" aria-label="Date for week {{ $night->week }}">
                        <button class="ed" type="submit">Move</button>
                    </span>
                </form>
            @endforeach
        </div>
    @endif

    @if ($locked)
        <div class="card">
            <p style="margin: 0">Results or subs are already recorded, so the fixtures are locked. To start again, reset the season or delete it on the <a href="{{ route('admin.season.index') }}">Season</a> page.</p>
        </div>
    @else
        <div class="card">
            <h2 class="h2">Generate fixtures</h2>
            <p class="sub">Builds a round robin from the {{ $playerCount }} players, with the biggest matches (strong v strong) towards the end of the season. You’ll see a preview first, and can regenerate it.@if ($league->hasFixtures()) Saving a new draft replaces the saved fixtures.@endif</p>
            <form method="POST" action="{{ route('admin.fixtures.generate') }}" class="stack">
                @csrf
                <div class="cols">
                    <div>
                        <label class="label" for="start_on">First league night (a Friday)</label>
                        <input class="field" id="start_on" name="start_on" type="date" value="{{ $startOn }}" required data-weeks-off-start>
                    </div>
                    <div>
                        <label class="label" for="max_weeks">Most league nights (optional)</label>
                        <input class="field" id="max_weeks" name="max_weeks" type="number" min="1" max="60" inputmode="numeric" value="{{ $form['max_weeks'] }}">
                        <p class="hint">Leave empty for a full round robin. A smaller number squeezes matches in, so some players play twice on a night.</p>
                    </div>
                </div>
                <fieldset style="border: 0; padding: 0; margin: 0">
                    <legend class="label">Weeks off (no league night)</legend>
                    <p class="hint" style="margin: 0 0 8px">Tick any Friday with no league night. Fixtures skip it and no subs are due.</p>
                    <div class="checks" data-weeks-off data-count="30">
                        @foreach ($fridays as $d)
                            <label><input type="checkbox" name="off[]" value="{{ $d->toDateString() }}" @checked(in_array($d->toDateString(), $offChecked, true))> {{ $d->format('D j M Y') }}</label>
                        @endforeach
                    </div>
                </fieldset>
                <button class="btn" type="submit" @disabled($playerCount < 2)>Preview fixtures</button>
                @if ($playerCount < 2)<p class="hint">Upload the players first.</p>@endif
            </form>
        </div>

        <div class="card">
            <h2 class="h2">Or upload fixtures</h2>
            <p class="sub">CSV with four columns: week, date (2026-11-13 or 13/11/2026), player 1, player 2. Names must match the players list.</p>
            <form method="POST" action="{{ route('admin.fixtures.manual') }}" enctype="multipart/form-data" class="stack">
                @csrf
                <div>
                    <label class="label" for="csv">CSV file</label>
                    <input class="field" id="csv" name="csv" type="file" accept=".csv,text/csv,text/plain">
                </div>
                <div>
                    <label class="label" for="text">Or paste the rows</label>
                    <textarea class="field" id="text" name="text" placeholder="Week,Date,Player 1,Player 2&#10;1,2026-11-13,Fiona M,Callum F">{{ old('text') }}</textarea>
                </div>
                <button class="btn ghost" type="submit">Check fixtures</button>
            </form>
        </div>
    @endif
</x-layouts.admin>
