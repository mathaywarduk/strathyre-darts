@use('App\Support\Dates')
<x-layouts.admin title="Results">
    <h1 class="h2" style="margin-top: 16px">Results</h1>
    <p class="sub">Pick the week, then paste one result per line: the winner, then the checkout they won with.</p>

    <form method="POST" action="{{ route('admin.results.preview') }}" class="card stack">
        @csrf
        <div>
            <label class="label" for="week">Week</label>
            <select class="field" id="week" name="week">
                @foreach ($league->nights as $night)
                    <option value="{{ $night->id }}" @selected($selected === $night->id)>
                        Week {{ $night->week }} · {{ Dates::short($night->date) }}@if ($night->isSubmitted()) · results in @endif
                    </option>
                @endforeach
            </select>
        </div>
        <div>
            <label class="label" for="lines">Results</label>
            <textarea class="field" id="lines" name="lines" style="min-height: 220px" placeholder="Callum F beat Isla C 121&#10;Fiona M beat Euan G 40" autocapitalize="off" spellcheck="false">{{ old('lines') }}</textarea>
            <p class="hint">Names can be in any case. The checkout is a whole number from {{ config('league.min_checkout') }} to {{ config('league.max_checkout') }}. Each line finds that pair’s fixture, whichever week it’s in, so catch-ups and early matches go in the same way. Fixtures in the chosen week with no result become catch-ups.</p>
        </div>
        <div class="actions">
            <button class="btn" type="submit" name="mode" value="results">Preview results</button>
            <button class="btn ghost" type="submit" name="mode" value="none" formnovalidate>No results this week</button>
        </div>
    </form>

    <p class="mute">Spotted a mistake? Use the Edit button next to the result on the <a href="{{ route('home') }}">home page</a> or a player’s page.</p>
</x-layouts.admin>
