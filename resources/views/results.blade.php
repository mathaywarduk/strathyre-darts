@use('App\Support\Dates')
<x-layouts.site title="Results and fixtures" section="results">
    <section class="hero" style="padding-top: 16px">
        <h1 class="page-title" style="font-size: 48px">Results and fixtures</h1>
    </section>

    <div class="narrow">
        @if ($pendingNight)
            <p class="notice" role="status">
                Week {{ $pendingNight->week }} results ({{ Dates::long($pendingNight->date) }}) haven’t been entered yet.
                @if ($pendingNight->id !== $night->id)
                    <a href="{{ route('results.index', ['week' => $pendingNight->week]) }}">View week {{ $pendingNight->week }}</a>
                @endif
                @auth
                    <a href="{{ route('admin.results.index') }}">Enter them</a>
                @endauth
            </p>
        @endif

        <div id="week-panel">
        <form method="GET" action="{{ route('results.index') }}" class="weekpick">
            <label class="sr-only" for="week">Choose a week</label>
            <select class="field" id="week" name="week">
                @foreach ($nights as $n)
                    <option value="{{ $n->week }}" @selected($n->id === $night->id)>Week {{ $n->week }} · {{ Dates::short($n->date) }}</option>
                @endforeach
            </select>
            <noscript><button class="btn ghost" type="submit">Show week</button></noscript>
        </form>

        <div class="weeknav">
            @if ($previous)
                <a class="wn-btn" href="{{ route('results.index', ['week' => $previous->week]) }}" data-dir="prev" aria-label="Previous week: week {{ $previous->week }}">‹</a>
            @else
                <span class="wn-btn off" aria-hidden="true">‹</span>
            @endif
            <div class="wn-title" aria-live="polite">
                <h2 class="h2" style="margin: 0">Week {{ $night->week }}</h2>
                <div class="mute">{{ Dates::long($night->date) }}</div>
            </div>
            @if ($next)
                <a class="wn-btn" href="{{ route('results.index', ['week' => $next->week]) }}" data-dir="next" aria-label="Next week: week {{ $next->week }}">›</a>
            @else
                <span class="wn-btn off" aria-hidden="true">›</span>
            @endif
        </div>

        <section>
            @foreach ($fixtures as $f)
                @php($res = $f->result)
                @if ($res)
                    <div class="line">
                        <span><span style="font-weight: 700">{{ $league->name($res->winner_id) }}</span> <span class="v">beat</span> {{ $league->name($res->loserId()) }}</span>
                        <span class="right">
                            <span><span class="mute">Checkout:</span> <strong>{{ $res->checkout }}</strong></span>
                            @auth
                                <a class="ed" href="{{ route('admin.results.edit', [$res, 'back' => request()->getRequestUri()]) }}" aria-label="Edit result: {{ $league->name($res->winner_id) }} v {{ $league->name($res->loserId()) }}">Edit</a>
                            @endauth
                        </span>
                    </div>
                @else
                    {{-- Not played: highlighted, with why --}}
                    <div class="line fx-unplayed">
                        <span>{{ $league->name($f->player1_id) }} <span class="v">v</span> {{ $league->name($f->player2_id) }}</span>
                        <span class="right">
                            <span class="tag">{{ $night->isSubmitted() ? 'Postponed' : ($night->date->lt(today()) ? 'Result to come' : 'To play') }}</span>
                            @auth
                                <a class="ed" href="{{ route('admin.results.create', [$f, 'back' => request()->getRequestUri()]) }}" aria-label="Add result: {{ $league->name($f->player1_id) }} v {{ $league->name($f->player2_id) }}">Add result</a>
                            @endauth
                        </span>
                    </div>
                @endif
            @endforeach
            @if ($byes->isNotEmpty())
                <div class="line"><span class="mute">Bye: {{ $byes->pluck('name')->implode(', ') }}</span></div>
            @endif
        </section>
        </div>
    </div>
    <script src="{{ asset('js/weeks.js') }}?v={{ filemtime(public_path('js/weeks.js')) }}" defer></script>
</x-layouts.site>
