@use('App\Support\Dates')
@use('App\Support\Money')
<x-layouts.site :title="'Week '.$night->week.' subs'" theme="light" section="finance">
    <div style="padding-top: 8px"><a class="back" href="{{ route('finance.index') }}">Back to finance</a></div>
    <section class="hero" style="padding-top: 16px">
        <h1 class="page-title">Week {{ $night->week }} subs</h1>
        <p class="mute" style="margin: 8px 0 0; font-size: 16px">{{ Dates::long($night->date) }} @unless ($finance->isDue($night))<span class="tag" style="margin-left: 8px">Not due yet</span>@endunless</p>
        <div class="stats3 tight">
            <div><div class="stat-n">{{ Money::format($week['received']) }}</div><div class="mute">Received</div></div>
            <div><div class="stat-n">{{ Money::format($week['expected']) }}</div><div class="mute">Due</div></div>
            <div><div class="owed">{{ Money::format($week['owed']) }}</div><div class="mute">Owed</div></div>
        </div>
    </section>

    <section class="sec first">
        <h2 class="h2">Not paid ({{ $week['unpaid']->count() }})</h2>
        @if ($week['unpaid']->isEmpty())
            <p style="margin: 8px 0 0">Everyone paid this week.</p>
        @endif
        @foreach ($week['unpaid'] as $p)
            <div class="line" id="p{{ $p->id }}">
                <a class="nm" style="font-weight: 600" href="{{ route('players.show', [$p, 'from' => 'week.'.$night->week]) }}">{{ $p->name }}</a>
                <span class="right">
                    @if ($finance->isDue($night))
                        <span class="owed-text" style="font-weight: 700">Owes {{ Money::format($finance->rate) }}</span>
                    @else
                        <span class="mute">Not due yet</span>
                    @endif
                    <form method="POST" action="{{ route('finance.toggle', [$night->week, $p]) }}">@csrf<button class="ed" type="submit">Mark paid</button></form>
                </span>
            </div>
        @endforeach
    </section>

    <section class="sec">
        <h2 class="h2">Paid ({{ $week['paid']->count() }})</h2>
        @foreach ($week['paid'] as $p)
            <div class="line" id="p{{ $p->id }}">
                <a class="nm" href="{{ route('players.show', [$p, 'from' => 'week.'.$night->week]) }}">{{ $p->name }}</a>
                <span class="right">
                    <span class="mute">Paid {{ Money::format($finance->rate) }}</span>
                    <form method="POST" action="{{ route('finance.toggle', [$night->week, $p]) }}">@csrf<button class="ed" type="submit" aria-label="Undo payment for {{ $p->name }}">Undo</button></form>
                </span>
            </div>
        @endforeach
    </section>
</x-layouts.site>
