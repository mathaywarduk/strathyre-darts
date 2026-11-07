@use('App\Support\Dates')
@use('App\Support\Money')
<x-layouts.site :title="'Week '.$night->week.' subs'" section="finance">
    <div style="padding-top: 8px"><a class="back" href="{{ route('finance.received') }}">Back to subs received</a></div>
    <section class="hero" style="padding-top: 16px">
        <h1 class="page-title">Week {{ $night->week }} subs</h1>
        <p class="mute" style="margin: 8px 0 0; font-size: 16px">{{ Dates::long($night->date) }}</p>
        @include('partials.subs-updated', ['updated' => $finance->league->season->subs_updated_at])
        <div class="stats3 tight">
            <div><div class="stat-n">{{ Money::format($week['received']) }}</div><div class="mute">Received</div></div>
            <div><div class="stat-n">{{ Money::format($week['expected']) }}</div><div class="mute">Due</div></div>
            <div><div class="owed">{{ Money::format($week['owed']) }}</div><div class="mute">Owed</div></div>
        </div>
    </section>

    <div class="grid-2">
    <section class="sec first">
        <h2 class="h2">Not paid ({{ $week['unpaid']->count() }})</h2>
        @if ($week['unpaid']->isEmpty())
            <p style="margin: 8px 0 0">Everyone paid this week.</p>
        @endif
        @foreach ($week['unpaid'] as $p)
            @php($owes = $finance->rate - $week['covered'][$p->id])
            <div class="line" id="p{{ $p->id }}">
                <a class="nm" style="font-weight: 600" href="{{ route('players.show', [$p, 'from' => 'week.'.$night->week]) }}">{{ $p->name }}</a>
                <span class="owed-text" style="font-weight: 700">Owes {{ Money::format($owes) }}@if ($week['covered'][$p->id] > 0) <span class="mute" style="font-weight: 400">(part-paid)</span>@endif</span>
            </div>
        @endforeach
    </section>

    <section class="sec">
        <h2 class="h2">Paid ({{ $week['paid']->count() }})</h2>
        @foreach ($week['paid'] as $p)
            <div class="line" id="p{{ $p->id }}">
                <a class="nm" href="{{ route('players.show', [$p, 'from' => 'week.'.$night->week]) }}">{{ $p->name }}</a>
                <span class="mute">Paid {{ Money::format($finance->rate) }}</span>
            </div>
        @endforeach
    </section>

    </div>
    @auth
        <p class="mute sec"><a href="{{ route('admin.subs') }}">Record a payment</a></p>
    @endauth
</x-layouts.site>
