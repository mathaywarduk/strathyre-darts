@use('App\Support\Money')
<x-layouts.site title="Subs received" section="finance">
    @include('partials.finance-nav', ['current' => 'received'])
    <section class="hero" style="padding-top: 16px">
        <h1 class="page-title">Subs received</h1>
        <div class="finance-balance" style="font-size: 64px; margin-top: 12px">{{ Money::format($finance->received()) }}</div>
        @include('partials.subs-updated', ['updated' => $finance->league->season->subs_updated_at])
    </section>

    <section class="sec first">
        @include('partials.week-key')
        <div class="narrow">
        @foreach ($players as $p)
            @php($weeks = collect($finance->player($p->id)['cells']))
            <div class="paid-row">
                <div class="top">
                    <a class="nm" style="font-weight: 600" href="{{ route('players.show', [$p, 'from' => 'received']) }}">{{ $p->name }}</a>
                    <span style="font-weight: 600" @class(['mute' => $finance->paidBy($p->id) === 0])>{{ Money::format($finance->paidBy($p->id)) }}</span>
                </div>
                @if ($weeks->isEmpty())
                    <div class="mute">No weeks played yet</div>
                @else
                    <div class="week-strip" tabindex="0" role="group" aria-label="Weeks for {{ $p->name }}">
                        @foreach ($weeks as $cell)
                            @if ($cell['ahead'])
                                <span class="week-btn paid ahead" aria-label="Week {{ $cell['night']->week }}, paid in advance"><small>Week</small>{{ $cell['night']->week }}</span>
                            @else
                                <a class="week-btn {{ $cell['paid'] ? 'paid' : 'unpaid' }}" href="{{ route('finance.week', $cell['night']->week) }}" aria-label="Week {{ $cell['night']->week }}, {{ $cell['paid'] ? 'paid' : 'not paid in full' }}"><small>Week</small>{{ $cell['night']->week }}</a>
                            @endif
                        @endforeach
                    </div>
                @endif
            </div>
        @endforeach
        </div>
    </section>
</x-layouts.site>
