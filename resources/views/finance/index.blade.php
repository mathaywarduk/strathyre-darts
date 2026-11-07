@use('App\Support\Money')
<x-layouts.site title="Finance" section="finance">
    <div class="fin-top">
    <section class="hero">
        <div class="mute" style="font-size: 15px">Balance</div>
        <div class="finance-balance">{{ Money::format($finance->balance()) }}</div>
        @if ($finance->opening() !== 0)
            <p class="mute" style="margin: 8px 0 0">Includes a starting balance of {{ Money::format($finance->opening()) }}</p>
        @endif
    </section>

    <section class="sec first">
        <div class="stats3">
            <a class="stat-link" href="{{ route('finance.received') }}">
                <div class="stat-n">{{ Money::format($finance->received()) }}</div>
                <div class="mute">Subs received ›</div>
            </a>
            <a class="stat-link" href="{{ route('finance.expenses') }}">
                <div class="stat-n">{{ Money::format($finance->expenses()) }}</div>
                <div class="mute">Expenses ›</div>
            </a>
            <a class="stat-link" href="{{ route('finance.owed') }}">
                <div class="owed">{{ Money::format($finance->owed()) }}</div>
                <div class="mute">Subs owed ›</div>
            </a>
        </div>
        @include('partials.subs-updated', ['updated' => $finance->league->season->subs_updated_at])
    </section>
    </div>
</x-layouts.site>
