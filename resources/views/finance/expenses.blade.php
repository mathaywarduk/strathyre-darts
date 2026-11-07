@use('App\Support\Dates')
@use('App\Support\Money')
<x-layouts.site title="Expenses" section="finance">
    <div style="padding-top: 8px"><a class="back" href="{{ route('finance.index') }}">Back to finance</a></div>
    <section class="hero" style="padding-top: 16px">
        <h1 class="page-title">Expenses</h1>
        <div class="finance-balance" style="font-size: 64px; margin-top: 12px">{{ Money::format($finance->expenses()) }}</div>
    </section>

    <section class="sec first narrow">
        @forelse ($expenses as $e)
            <div class="line-row expense">
                <span class="mute">{{ Dates::short($e->spent_on) }}</span>
                <span>{{ $e->item }}</span>
                <span style="font-weight: 600">{{ Money::format($e->cost_pence) }}</span>
            </div>
        @empty
            <p class="sub">No expenses yet.</p>
        @endforelse
        @auth
            <p class="mute sec"><a href="{{ route('admin.expenses.index') }}">Add or edit expenses</a></p>
        @endauth
    </section>
</x-layouts.site>
