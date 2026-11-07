@use('App\Support\Dates')
<x-layouts.site title="Results" section="results">
    <section class="hero" style="padding-top: 16px">
        <h1 class="page-title">Results</h1>
    </section>

    @if ($pendingNight)
        <p class="notice" role="status">
            Week {{ $pendingNight->week }} results ({{ Dates::long($pendingNight->date) }}) haven’t been entered yet.
            @auth
                <a href="{{ route('admin.results.index') }}">Enter them</a>
            @endauth
        </p>
    @endif

    @forelse ($weeks as $w)
        <section class="sec {{ $loop->first ? 'first' : '' }}">
            <h2 class="h2">Week {{ $w['night']->week }}</h2>
            <p class="sub">{{ Dates::long($w['night']->date) }}</p>
            @foreach ($w['fixtures'] as $f)
                @php($res = $f->result)
                <div class="line">
                    <span><span style="font-weight: 700">{{ $league->name($res->winner_id) }}</span> <span class="v">beat</span> {{ $league->name($res->loserId()) }}</span>
                    <span class="right">
                        <span><span class="mute">Checkout:</span> <strong>{{ $res->checkout }}</strong></span>
                        @auth
                            <a class="ed" href="{{ route('admin.results.edit', [$res, 'back' => request()->getRequestUri()]) }}" aria-label="Edit result: {{ $league->name($res->winner_id) }} v {{ $league->name($res->loserId()) }}">Edit</a>
                        @endauth
                    </span>
                </div>
            @endforeach
        </section>
    @empty
        @unless ($pendingNight)
            <section class="sec first"><p class="sub">No results yet.</p></section>
        @endunless
    @endforelse
</x-layouts.site>
