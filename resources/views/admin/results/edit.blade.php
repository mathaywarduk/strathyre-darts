<x-layouts.admin title="Edit result">
    @php
        $a = $league->name($fixture->player1_id);
        $b = $league->name($fixture->player2_id);
    @endphp
    <p><a class="back" href="{{ $back }}">Cancel</a></p>
    <h1 class="h2">{{ $a }} v {{ $b }}</h1>
    <p class="sub">Week {{ $fixture->night->week }}</p>

    <form method="POST" action="{{ route('admin.results.update', $result) }}" class="card stack" data-save-shortcut>
        @csrf @method('PUT')
        <input type="hidden" name="back" value="{{ $back }}">
        <div>
            <label class="label" for="winner_id">Winner</label>
            <select class="field" id="winner_id" name="winner_id">
                @foreach ([$fixture->player1_id => $a, $fixture->player2_id => $b] as $id => $name)
                    <option value="{{ $id }}" @selected((int) old('winner_id', $result->winner_id) === $id)>{{ $name }}</option>
                @endforeach
            </select>
        </div>
        <div>
            <label class="label" for="checkout">Winner’s checkout</label>
            <input class="field" id="checkout" name="checkout" type="number" min="{{ config('league.min_checkout') }}" max="{{ config('league.max_checkout') }}" inputmode="numeric" value="{{ old('checkout', $result->checkout) }}" required>
            <p class="hint">The score they finished from, with three darts to go.</p>
        </div>
        <button class="btn" type="submit">Save</button>
    </form>

    <form method="POST" action="{{ route('admin.results.destroy', $result) }}" class="card" style="margin-top: 16px">
        @csrf @method('DELETE')
        <input type="hidden" name="back" value="{{ $back }}">
        <p style="margin: 0 0 12px">Entered against the wrong match? Clear the result and the match goes back to the fixtures, or to the catch-ups if its week is done.</p>
        <button class="btn danger" type="submit">Clear this result</button>
    </form>
</x-layouts.admin>
