<x-layouts.admin title="Check results">
    <h1 class="h2" style="margin-top: 16px">Week {{ $night->week }}: check before saving</h1>
    <p class="sub">Nothing is saved until you confirm.</p>

    @if ($mode === 'none')
        <div class="card">
            <h2 class="h2">No results this week</h2>
            <p>No league night results for week {{ $night->week }}. All {{ $preview['postponed']->count() }} matches become catch-ups and the site moves on to the next week. Subs are still due.</p>
        </div>
    @else
        <div class="card">
            <h2 class="h2">Results ({{ $preview['ok']->count() }})</h2>
            @forelse ($preview['ok'] as $row)
                <div class="weekblock" style="padding: 8px 0">
                    <span class="okline"><strong>{{ $row['winner']->name }}</strong> beat {{ $row['loser']->name }}, <strong>+{{ $row['remaining'] }}</strong></span>
                    @if ($row['note'])<span class="mute"> · {{ $row['note'] }}</span>@endif
                    @if ($row['replaces'])<div class="badline">Replaces the existing result: {{ $row['replaces'] }}</div>@endif
                </div>
            @empty
                <p class="mute" style="margin: 0">No results in the list.</p>
            @endforelse
        </div>
    @endif

    @if ($preview['errorCount'])
        <div class="card" style="border-color: var(--owed); border-width: 2px">
            <h2 class="h2">Lines that need fixing ({{ $preview['errorCount'] }})</h2>
            @foreach ($preview['rows'] as $row)
                @if ($row['error'])
                    <div class="weekblock" style="padding: 8px 0">
                        <div><code>{{ $row['line'] }}</code></div>
                        <div class="badline">{{ $row['error'] }}</div>
                    </div>
                @endif
            @endforeach
        </div>
    @endif

    @if ($mode === 'results')
        <div class="card">
            <h2 class="h2">Postponed ({{ $preview['postponed']->count() }})</h2>
            @if ($night->isSubmitted())
                <p class="mute" style="margin: 0">Week {{ $night->week }} was already submitted, so only these results are added.</p>
            @else
                <p class="sub">Matches this week with no result. They become catch-ups.</p>
                @forelse ($preview['postponed'] as $f)
                    <div class="line"><span>{{ $league->name($f->player1_id) }} <span class="v">v</span> {{ $league->name($f->player2_id) }}</span></div>
                @empty
                    <p style="margin: 0">None. Every match this week has a result.</p>
                @endforelse
            @endif
        </div>
    @else
        <div class="card">
            <h2 class="h2">Becoming catch-ups</h2>
            @foreach ($preview['postponed'] as $f)
                <div class="line"><span>{{ $league->name($f->player1_id) }} <span class="v">v</span> {{ $league->name($f->player2_id) }}</span></div>
            @endforeach
        </div>
    @endif

    <div class="actions">
        @if ($preview['errorCount'] === 0)
            <form method="POST" action="{{ route('admin.results.save') }}">
                @csrf
                <input type="hidden" name="week" value="{{ $night->id }}">
                <input type="hidden" name="mode" value="{{ $mode }}">
                <input type="hidden" name="lines" value="{{ $lines }}">
                <button class="btn" type="submit">{{ $mode === 'none' ? 'Confirm: no results' : 'Confirm and save' }}</button>
            </form>
        @endif
        <form method="POST" action="{{ route('admin.results.back') }}">
            @csrf
            <input type="hidden" name="week" value="{{ $night->id }}">
            <input type="hidden" name="lines" value="{{ $lines }}">
            <button class="btn ghost" type="submit">{{ $preview['errorCount'] ? 'Fix the lines' : 'Go back' }}</button>
        </form>
    </div>
</x-layouts.admin>
