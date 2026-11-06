@use('App\Support\Dates')
<x-layouts.admin title="Preview fixtures">
    <h1 class="h2" style="margin-top: 16px">Preview fixtures</h1>
    <p class="sub">
        {{ count($draft['weeks']) }} weeks, {{ collect($draft['weeks'])->sum(fn ($w) => count($w['matches'])) }} matches.
        @if ($doubles) {{ $doubles }} {{ $doubles === 1 ? 'player plays' : 'players play' }} twice on a night. @endif
        Nothing is saved until you save.
    </p>

    <div class="actions" style="margin-bottom: 8px">
        <form method="POST" action="{{ route('admin.fixtures.save') }}">@csrf<input type="hidden" name="token" value="{{ $draft['token'] }}"><button class="btn" type="submit">Save fixtures</button></form>
        @if ($draft['source'] === 'generated')
            <form method="POST" action="{{ route('admin.fixtures.generate') }}">
                @csrf
                <input type="hidden" name="start_on" value="{{ $draft['start_on'] }}">
                <input type="hidden" name="max_weeks" value="{{ $draft['max_weeks'] }}">
                @foreach ($draft['off'] as $d)<input type="hidden" name="off[]" value="{{ $d }}">@endforeach
                <button class="btn ghost" type="submit">Regenerate</button>
            </form>
        @endif
        <form method="POST" action="{{ route('admin.fixtures.discard') }}">@csrf<button class="btn ghost" type="submit">Discard</button></form>
    </div>

    @if ($draft['source'] === 'generated')
        <p class="hint">Rankings are in brackets. The biggest matches, strong v strong, are placed towards the end of the season.</p>
    @endif

    @foreach ($draft['weeks'] as $week)
        <div class="weekblock">
            <h3><span>Week {{ $week['week'] }} <span class="mute" style="font-weight: 400">{{ Dates::long(\Carbon\CarbonImmutable::parse($week['date'])) }}</span></span></h3>
            <ul>
                @foreach ($week['matches'] as [$a, $b])
                    <li>{{ $names[$a] }} <span class="mute">({{ $ranks[$a] }})</span> <span class="v">v</span> {{ $names[$b] }} <span class="mute">({{ $ranks[$b] }})</span></li>
                @endforeach
            </ul>
            @if (! empty($byes[$week['week']]))
                <p class="mute" style="margin: 6px 0 0">Bye: {{ implode(', ', $byes[$week['week']]) }}</p>
            @endif
        </div>
    @endforeach
</x-layouts.admin>
