@use('App\Support\Money')
@use('App\Services\Finance')
<x-layouts.site title="Subs owed" section="finance">
    <div style="padding-top: 8px"><a class="back" href="{{ route('finance.index') }}">Back to finance</a></div>
    <section class="hero" style="padding-top: 16px">
        <h1 class="page-title">Subs owed</h1>
        <div class="finance-balance" style="font-size: 64px; margin-top: 12px"><span class="owed-text">{{ Money::format($finance->owed()) }}</span></div>
        @include('partials.subs-updated', ['updated' => $finance->league->season->subs_updated_at])
    </section>

    <section class="sec first">
        <div class="cols-2">
        @forelse ($owing as $o)
            <div class="owing-row">
                <a class="nm" style="font-weight: 600" href="{{ route('players.show', [$o['player'], 'from' => 'owed']) }}">{{ $o['player']->name }}</a>
                <span class="owed">{{ Money::format($o['amount']) }}</span>
                <span class="mute">{{ Finance::weeksText($o['weeks'], true) }}</span>
            </div>
        @empty
            <p style="margin: 8px 0 0">Nobody owes anything.</p>
        @endforelse
        </div>
        @auth
            <p class="mute sec"><a href="{{ route('admin.subs') }}">Record a payment</a></p>
        @endauth
    </section>
</x-layouts.site>
