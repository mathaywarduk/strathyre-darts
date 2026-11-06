@use('App\Support\Dates')
@use('App\Support\Money')
<x-layouts.admin title="Expenses">
    <h1 class="h2" style="margin-top: 16px">Expenses</h1>

    <form method="POST" action="{{ route('admin.expenses.store') }}" class="card stack">
        @csrf
        <h2 class="h2">Add an expense</h2>
        <div>
            <label class="label" for="item">Item</label>
            <input class="field" id="item" name="item" value="{{ old('item') }}" required>
        </div>
        <div class="cols">
            <div>
                <label class="label" for="cost">Cost (£)</label>
                <input class="field" id="cost" name="cost" inputmode="decimal" value="{{ old('cost') }}" required>
            </div>
            <div>
                <label class="label" for="spent_on">Date</label>
                <input class="field" id="spent_on" name="spent_on" type="date" value="{{ old('spent_on', today()->toDateString()) }}" required>
            </div>
        </div>
        <button class="btn" type="submit">Add expense</button>
    </form>

    <section class="sec first">
        <p class="sub">{{ $expenses->count() }} this season, {{ Money::format($expenses->sum('cost_pence')) }} in total.</p>
        @foreach ($expenses as $e)
            <div class="line">
                <span><span class="mute">{{ Dates::short($e->spent_on) }}</span> &nbsp;{{ $e->item }}</span>
                <span class="right">
                    <strong>{{ Money::format($e->cost_pence) }}</strong>
                    <a class="ed" href="{{ route('admin.expenses.edit', $e) }}">Edit</a>
                </span>
            </div>
        @endforeach
    </section>
</x-layouts.admin>
