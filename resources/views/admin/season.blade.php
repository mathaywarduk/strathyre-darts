@use('App\Support\Dates')
@use('App\Support\Money')
<x-layouts.admin title="Season">
    <h1 class="h2" style="margin-top: 16px">Season</h1>

    @if ($current)
        <div class="card">
            <p class="mute" style="margin: 0">Current season</p>
            <p class="big" style="font-size: 40px; margin: 4px 0">{{ $current->label }}</p>
            <p style="margin: 0">Starts {{ Dates::long($current->starts_on) }} {{ $current->starts_on->year }}.
                @if ($league->hasFixtures()) {{ $league->weeksPlayed() }} of {{ $league->totalWeeks() }} weeks played. @else No fixtures yet. @endif
            </p>
        </div>

        <form method="POST" action="{{ route('admin.season.balance') }}" class="card stack" data-save-shortcut>
            @csrf @method('PUT')
            <h2 class="h2">Starting balance</h2>
            <p class="sub" style="margin: 0">Money already in the pot when this season began, for example left over from last year. It’s added to the balance on the Finance page. A new season starts with last season’s closing balance, and you can change it here.</p>
            <div>
                <label class="label" for="balance">Amount (£)</label>
                <input class="field" id="balance" name="balance" inputmode="decimal" value="{{ old('balance', Money::input($current->opening_pence)) }}" required>
            </div>
            <button class="btn" type="submit">Save starting balance</button>
        </form>

        <form method="POST" action="{{ route('admin.season.reset') }}" class="card stack">
            @csrf
            <h2 class="h2">Start a new season</h2>
            <p class="sub" style="margin: 0">This ends the current season. Its final table and results are kept in the past seasons list, the money balance carries forward, and results, subs and fixtures start fresh. The players stay.</p>
            @if ($league->hasFixtures() && ($league->currentNight() || $league->catchUps()->isNotEmpty()))
                <p class="owed-text" style="margin: 0; font-weight: 600">The season isn’t finished: {{ $league->totalWeeks() - $league->weeksPlayed() }} weeks and {{ $league->catchUps()->count() }} catch-ups are still to play.</p>
            @endif
            <div>
                <label class="label" for="starts_on">First league night of the new season (a Friday)</label>
                <input class="field" id="starts_on" name="starts_on" type="date" value="{{ old('starts_on', $suggested) }}" required>
            </div>
            <div>
                <label class="label" for="confirm">Type RESET to confirm</label>
                <input class="field" id="confirm" name="confirm" autocomplete="off" required>
            </div>
            <button class="btn danger" type="submit">End season and start the next</button>
        </form>
    @else
        <form method="POST" action="{{ route('admin.season.start') }}" class="card stack">
            @csrf
            <h2 class="h2">Start a season</h2>
            <p class="sub" style="margin: 0">There’s no current season.</p>
            <div>
                <label class="label" for="starts_on">First league night (a Friday)</label>
                <input class="field" id="starts_on" name="starts_on" type="date" value="{{ old('starts_on', '2026-11-13') }}" required>
            </div>
            <button class="btn" type="submit">Start season</button>
        </form>
    @endif

    <section class="sec">
        <h2 class="h2">All seasons</h2>
        <p class="sub">Past seasons are public. Deleting is permanent: it removes the season’s fixtures, results, subs and expenses for good. It’s meant for clearing out test data.</p>
        @foreach ($current ? collect([$current])->concat($archived) : $archived as $s)
            <form method="POST" action="{{ route('admin.season.destroy', $s) }}" class="card">
                @csrf @method('DELETE')
                <p style="margin: 0 0 8px"><strong>{{ $s->label }}</strong> <span class="mute">starting {{ Dates::short($s->starts_on) }} {{ $s->starts_on->year }} · {{ $s->isArchived() ? 'archived' : 'current' }}</span>
                    @if ($s->isArchived()) · <a href="{{ route('seasons.show', $s) }}">View</a>@endif</p>
                <div class="actions">
                    <input class="field" style="max-width: 220px" name="confirm" placeholder="Type DELETE" autocomplete="off" aria-label="Type DELETE to delete season {{ $s->label }}" required>
                    <button class="btn danger" type="submit">Delete permanently</button>
                </div>
            </form>
        @endforeach
    </section>
</x-layouts.admin>
