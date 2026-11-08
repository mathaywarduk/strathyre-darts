<x-layouts.admin title="Score a match">
    @php
        [$a, $b] = $teams;
        $names = fn ($t) => implode(' & ', $t->names());
    @endphp
    <p><a class="back" href="{{ $back }}">Cancel</a></p>
    <h1 class="h2">{{ $names($a) }} <span class="mute">v</span> {{ $names($b) }}</h1>
    <p class="sub">{{ $roundName }}, match {{ $match->position }}</p>

    <form method="POST" action="{{ route('admin.doubles.score', $match) }}" class="card stack" data-save-shortcut>
        @csrf @method('PUT')
        <input type="hidden" name="back" value="{{ $back }}">
        <fieldset class="plain-fieldset">
            <legend class="label">Who won?</legend>
            <div class="picks one">
                @foreach ([$a, $b] as $team)
                    <label class="pick">
                        <input type="radio" name="winner_team_id" value="{{ $team->id }}" @checked((int) old('winner_team_id', $match->winner_team_id) === $team->id) required>
                        <span>{{ $names($team) }}</span>
                    </label>
                @endforeach
            </div>
        </fieldset>
        <div>
            <label class="label" for="checkout">Winning checkout (optional)</label>
            <input class="field" id="checkout" name="checkout" type="number" min="{{ config('league.min_checkout') }}" max="{{ config('league.max_checkout') }}" inputmode="numeric" value="{{ old('checkout', $match->checkout) }}" style="max-width: 200px">
            <p class="hint">The score they finished from, with three darts to go. Leave it empty if you don’t have it.</p>
        </div>
        <button class="btn" type="submit">{{ $match->isPlayed() ? 'Save' : 'Save result' }}</button>
    </form>

    @if ($match->isPlayed())
        <form method="POST" action="{{ route('admin.doubles.clear', $match) }}" class="card" style="margin-top: 16px">
            @csrf @method('DELETE')
            <input type="hidden" name="back" value="{{ $back }}">
            <p style="margin: 0 0 12px">Entered against the wrong match? Clear it and the match can be played again. This isn’t possible once the next round has been played.</p>
            <button class="btn danger" type="submit">Clear this result</button>
        </form>
    @endif
</x-layouts.admin>
