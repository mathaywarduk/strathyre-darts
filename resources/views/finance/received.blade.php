@use('App\Support\Dates')
@use('App\Support\Money')
<x-layouts.site title="Subs received" section="finance">
    <div style="padding-top: 8px"><a class="back" href="{{ route('finance.index') }}">Back to finance</a></div>
    <section class="hero" style="padding-top: 16px">
        <h1 class="page-title">Subs received</h1>
        <div class="finance-balance" style="font-size: 64px; margin-top: 12px">{{ Money::format($finance->received()) }}</div>
        @include('partials.subs-updated', ['updated' => $finance->league->season->subs_updated_at])
    </section>

    <section class="sec first">
        <h2 class="h2">Week by week</h2>
        @forelse ($weeks as $w)
            <div class="week-row">
                <div class="top">
                    <span><a class="nm" style="font-weight: 600" href="{{ route('finance.week', $w['night']->week) }}">Week {{ $w['night']->week }}</a> <span class="mute">{{ Dates::short($w['night']->date) }}</span></span>
                    <span style="font-weight: 600">{{ Money::format($w['received']) }}</span>
                </div>
                <div class="mid mute"><span>{{ $w['paid']->count() }} of {{ $w['paid']->count() + $w['unpaid']->count() }} paid</span><span>of {{ Money::format($w['expected']) }}</span></div>
                @if ($w['unpaid']->isNotEmpty())
                    <div class="missed">Missed: {{ $w['unpaid']->map(fn ($p) => $p->name.($w['covered'][$p->id] > 0 ? ' ('.Money::format($w['covered'][$p->id]).' paid)' : ''))->implode(', ') }}</div>
                @endif
            </div>
        @empty
            <p class="sub">No league nights yet.</p>
        @endforelse
    </section>
</x-layouts.site>
