@use('App\Support\Money')
<x-layouts.site title="Finance" section="finance">
    @include('partials.finance-nav', ['current' => 'overview'])
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
            <div>
                <div class="stat-label">Subs received</div>
                <div class="stat-n">{{ Money::format($finance->received()) }}</div>
            </div>
            <div>
                <div class="stat-label">Subs owed</div>
                <div class="owed">{{ Money::format($finance->owed()) }}</div>
            </div>
            <div>
                <div class="stat-label">Expenses</div>
                <div class="stat-n">{{ Money::format($finance->expenses()) }}</div>
            </div>
        </div>
        @include('partials.subs-updated', ['updated' => $finance->league->season->subs_updated_at])
    </section>
    </div>
</x-layouts.site>
