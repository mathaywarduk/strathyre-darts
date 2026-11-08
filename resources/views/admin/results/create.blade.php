@use('App\Support\Dates')
<x-layouts.admin title="Add result">
    @php
        $a = $league->name($fixture->player1_id);
        $b = $league->name($fixture->player2_id);
    @endphp
    <p><a class="back" href="{{ $back }}">Cancel</a></p>
    <h1 class="h2">{{ $a }} v {{ $b }}</h1>
    <p class="sub">Week {{ $fixture->night->week }}, {{ Dates::long($fixture->night->date) }}</p>

    <form method="POST" action="{{ route('admin.results.store', $fixture) }}" class="card stack" data-save-shortcut>
        @csrf
        <input type="hidden" name="back" value="{{ $back }}">
        <div>
            <label class="label" for="winner_id">Winner</label>
            <select class="field" id="winner_id" name="winner_id" required>
                <option value="">Choose the winner</option>
                @foreach ([$fixture->player1_id => $a, $fixture->player2_id => $b] as $id => $name)
                    <option value="{{ $id }}" @selected((int) old('winner_id') === $id)>{{ $name }}</option>
                @endforeach
            </select>
        </div>
        <div>
            <label class="label" for="checkout">Winner’s checkout</label>
            <input class="field" id="checkout" name="checkout" type="number" min="{{ config('league.min_checkout') }}" max="{{ config('league.max_checkout') }}" inputmode="numeric" value="{{ old('checkout') }}" required>
            <p class="hint">The score they finished from, with three darts to go.</p>
        </div>
        <button class="btn" type="submit">Add result</button>
    </form>
</x-layouts.admin>
