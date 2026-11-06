@use('App\Support\Dates')
<x-layouts.admin title="Dashboard">
    <h1 class="h2" style="margin-top: 16px">Admin</h1>

    @if ($live)
        <div class="card" style="margin-top: 16px">
            <p class="mute" style="margin: 0">Season {{ $season->label }}</p>
            @if ($night)
                <p class="big" style="font-size: 40px; margin: 4px 0 0">Week {{ $night->week }} of {{ $league->totalWeeks() }}</p>
                <p style="margin: 4px 0 12px">{{ Dates::long($night->date) }}</p>
                @if ($due)
                    <p class="owed-text" style="font-weight: 700; margin: 0 0 12px">Week {{ $night->week }} results are due.</p>
                @endif
                <div class="actions">
                    <a class="btn" href="{{ route('admin.results.index') }}">Enter week {{ $night->week }} results</a>
                    <a class="btn ghost" href="{{ route('admin.subs') }}">Record a subs payment</a>
                </div>
            @else
                <p class="big" style="font-size: 40px; margin: 4px 0 12px">Season complete</p>
                <a class="btn" href="{{ route('admin.results.index') }}">Enter results</a>
            @endif
            @if ($catchUps)
                <p class="mute" style="margin: 12px 0 0">{{ $catchUps }} {{ \Illuminate\Support\Str::plural('catch-up', $catchUps) }} still to play.</p>
            @endif
        </div>
    @else
        <div class="card" style="margin-top: 16px">
            <h2 class="h2">Get the league ready</h2>
            <p class="sub">The public site shows “Coming soon” until all of this is done.</p>
            <ul class="plain">
                @foreach ($setup as [$label, $done, $url, $note])
                    <li class="line">
                        <span class="{{ $done ? 'check-ok' : 'check-todo' }}"><a href="{{ $url }}">{{ $label }}</a>@if ($note) <span class="mute">({{ $note }})</span>@endif</span>
                    </li>
                @endforeach
            </ul>
        </div>
    @endif

    @if ($live || $season)
        <div class="card">
            <h2 class="h2">Quick links</h2>
            <ul class="plain">
                @if ($live)
                    <li class="line"><a class="nm" href="{{ route('admin.results.index') }}">Enter or check results</a></li>
                    <li class="line"><a class="nm" href="{{ route('admin.subs') }}">Record a subs payment, or see who owes</a></li>
                    <li class="line"><a class="nm" href="{{ route('finance.index') }}">Finance</a></li>
                @endif
                @if ($season)
                    <li class="line"><a class="nm" href="{{ route('admin.expenses.index') }}">Add an expense</a></li>
                @endif
            </ul>
        </div>
    @endif
</x-layouts.admin>
