@use('App\Support\Money')
<x-layouts.admin title="Edit expense">
    <p><a class="back" href="{{ route('admin.expenses.index') }}">Back to expenses</a></p>
    <h1 class="h2">Edit expense</h1>

    <form method="POST" action="{{ route('admin.expenses.update', $expense) }}" class="card stack" data-save-shortcut>
        @csrf @method('PUT')
        <div>
            <label class="label" for="item">Item</label>
            <input class="field" id="item" name="item" value="{{ old('item', $expense->item) }}" required>
        </div>
        <div class="cols">
            <div>
                <label class="label" for="cost">Cost (£)</label>
                <input class="field" id="cost" name="cost" inputmode="decimal" value="{{ old('cost', Money::input($expense->cost_pence)) }}" required>
            </div>
            <div>
                <label class="label" for="spent_on">Date</label>
                <input class="field" id="spent_on" name="spent_on" type="date" value="{{ old('spent_on', $expense->spent_on->toDateString()) }}" required>
            </div>
        </div>
        <button class="btn" type="submit">Save</button>
    </form>

    <form method="POST" action="{{ route('admin.expenses.destroy', $expense) }}" class="card" style="margin-top: 16px">
        @csrf @method('DELETE')
        <button class="btn danger" type="submit">Delete expense</button>
    </form>
</x-layouts.admin>
