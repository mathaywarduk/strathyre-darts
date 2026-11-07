@use('App\Support\Dates')
@use('App\Services\Finance')
@use('App\Support\Money')
@php
    $id = $player->id;
    $isW = $leaders['W'] === $id;
    $isM = $leaders['M'] === $id;
    $accent = $isW ? 'var(--women)' : ($isM ? 'var(--men)' : 'var(--fg)');
    $opp = fn ($f) => $league->name($f->opponentOf($id));
@endphp
<x-layouts.site :title="$player->name" :section="$back['section']">
    <div style="padding-top: 8px"><a class="back" href="{{ $back['url'] }}">{{ $back['label'] }}</a></div>

    <section class="hero" style="padding-top: 16px">
        <h1 class="page-title" style="color: {{ $accent }}">{{ $player->name }}</h1>
        @if ($isW)<p><span class="badge w">Women’s leader</span></p>@endif
        @if ($isM)<p><span class="badge m">Men’s leader</span></p>@endif
        @if ($holdsBestCheckout)<p><span class="badge co">Highest checkout</span></p>@endif
    </section>

    <div class="pcols">
    <div class="pcol-a">
    <section class="pc-stats" style="padding-top: 24px">
        <div class="stats3">
            <div><div class="big" style="font-size: 44px">{{ Dates::ordinal($row['pos']) }}{{ $row['tied'] ? '=' : '' }}</div><div class="mute">League position</div></div>
            <div><div class="big" style="font-size: 44px">{{ $row['pts'] }}</div><div class="mute">Points</div></div>
            <div><div class="big" style="font-size: 44px">{{ $highestCheckout ?? '–' }}</div><div class="mute">Highest checkout</div></div>
            <div><div class="big" style="font-size: 32px">{{ $row['played'] }}</div><div class="mute">Played</div></div>
            <div><div class="big" style="font-size: 32px">{{ $row['won'] }}</div><div class="mute">Won</div></div>
            <div><div class="big" style="font-size: 32px">{{ $row['lost'] }}</div><div class="mute">Lost</div></div>
        </div>
    </section>

    @if ($next || $catchUps->isNotEmpty())
        <section class="panels pc-panels">
            @if ($next)
                <div class="panel next">
                    <div class="mute">Next match</div>
                    <div class="big"><span class="v">v</span> {{ $opp($next) }}</div>
                    <div style="margin-top: 6px">Week {{ $next->night->week }}, {{ Dates::long($next->night->date) }}</div>
                </div>
            @endif
            @foreach ($catchUps as $f)
                <div class="panel">
                    <div class="mute">Catch-up from week {{ $f->night->week }}</div>
                    <div class="big"><span class="v">v</span> {{ $opp($f) }}</div>
                </div>
            @endforeach
        </section>
    @endif

    <section class="sec pc-subs">
        <h2 class="h2">Subs</h2>
        <p style="margin: 8px 0 14px; font-variant-numeric: tabular-nums">
            <span class="mute">Paid:</span> <strong>{{ Money::format($subs['paid']) }}</strong>
            <span style="display: inline-block; width: 20px"></span>
            <span class="mute">Owes:</span> <strong @class(['owed-text' => $subs['owed'] > 0])>{{ Money::format($subs['owed']) }}</strong>
        </p>
        @include('partials.subs-updated', ['updated' => $subsUpdated])
        <div class="week-grid" style="margin-top: 14px">
            @foreach ($subs['cells'] as $cell)
                <a class="week-btn {{ $cell['paid'] ? 'paid' : '' }}" href="{{ route('finance.week', $cell['night']->week) }}" aria-label="Week {{ $cell['night']->week }}, {{ $cell['paid'] ? 'paid' : 'not paid in full' }}"><small>Week</small>{{ $cell['night']->week }}</a>
            @endforeach
        </div>
    </section>
    </div>
    <div class="pcol-b">
    <section class="sec pc-fixtures">
        <h2 class="h2">Fixtures</h2>
        @forelse ($upcoming as $f)
            <div class="line-row">
                <span class="mute">Week {{ $f->night->week }}</span>
                <span><span class="v">v</span> {{ $opp($f) }}</span>
                <span class="mute">{{ Dates::short($f->night->date) }}</span>
            </div>
        @empty
            <p class="sub">No upcoming fixtures.</p>
        @endforelse
    </section>

    <section class="sec pc-results">
        <h2 class="h2">Results</h2>
        @forelse ($results as $f)
            @php
                $res = $f->result;
                $won = $res->winner_id === $id;
            @endphp
            <div class="line-row four">
                <span class="mute">Week {{ $f->night->week }}</span>
                <span><span class="v">v</span> {{ $opp($f) }}</span>
                <span class="{{ $won ? 'res-w' : 'res-l' }}">{{ $won ? 'W ('.$res->checkout.')' : 'L' }}</span>
                @auth
                    <a class="ed" href="{{ route('admin.results.edit', [$res, 'back' => request()->getRequestUri()]) }}" aria-label="Edit result: week {{ $f->night->week }} v {{ $opp($f) }}">Edit</a>
                @endauth
            </div>
        @empty
            <p class="sub">No results yet.</p>
        @endforelse
    </section>

    </div>
    </div>
</x-layouts.site>
