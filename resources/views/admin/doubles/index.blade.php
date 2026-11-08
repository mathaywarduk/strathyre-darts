@use('App\Support\Dates')
<x-layouts.admin title="Doubles">
    <h1 class="h2" style="margin-top: 16px">Doubles tournament</h1>

    @if ($tournament)
        @php
            $champions = $bracket->champions();
            $noneYet = $bracket->played() === 0;
        @endphp
        <p class="sub">{{ $tournament->name }}: {{ $bracket->teams->count() }} teams, {{ $bracket->played() }} of {{ $bracket->toPlay() }} matches played. It’s showing on the home page now.</p>

        @if ($champions)
            <div class="card" style="margin-bottom: 16px">
                <p class="mute" style="margin: 0">Champions</p>
                <p class="big" style="font-size: 36px; margin: 4px 0 8px">{{ $champions->label() }}</p>
                <p style="margin: 0">When you’re done, press Finish to put the league back on the home page.</p>
            </div>
        @else
            <p class="hint" style="margin-top: 0">Tap a match to add its result.</p>
        @endif

        @include('tournament.bracket', ['bracket' => $bracket, 'admin' => true, 'single' => true])

        <div class="card stack" style="margin-top: 24px">
            <div class="actions">
                <form method="POST" action="{{ route('admin.doubles.end') }}" onsubmit="return confirm('Finish the tournament and put the league back on the home page?')">
                    @csrf
                    <button class="btn" type="submit">Finish</button>
                </form>
                @if ($noneYet)
                    <form method="POST" action="{{ route('admin.doubles.redraw') }}" onsubmit="return confirm('Draw the teams and the knockout again?')">
                        @csrf
                        <button class="btn ghost" type="submit">Draw again</button>
                    </form>
                @endif
                <form method="POST" action="{{ route('admin.doubles.destroy') }}" onsubmit="return confirm('Delete this tournament and everything in it?')">
                    @csrf @method('DELETE')
                    <button class="btn ghost" type="submit">Delete</button>
                </form>
            </div>
            @if ($noneYet)
                <p class="hint" style="margin: 20px 0 0">No match has been played, so you can draw again if you don’t like the draw.</p>
            @endif
        </div>
    @else
        <p class="sub">The yearly knockout for pairs. Pick who’s turned up and it’s drawn at random. While it’s running, the home page shows the bracket instead of the league.</p>
        <p><a class="btn" href="{{ route('admin.doubles.create') }}">Start a doubles tournament</a></p>
    @endif

    @if ($past->isNotEmpty())
        <section class="sec">
            <h2 class="h2">Past tournaments</h2>
            @foreach ($past as $p)
                <div class="line">
                    <span><strong>{{ $p['tournament']->name }}</strong> <span class="mute">{{ $p['tournament']->ended_at ? Dates::dayMonth($p['tournament']->ended_at).' '.$p['tournament']->ended_at->year : '' }}</span></span>
                    <span>{{ $p['champions'] ? $p['champions']->label() : 'No champions' }}</span>
                </div>
            @endforeach
        </section>
    @endif
</x-layouts.admin>
