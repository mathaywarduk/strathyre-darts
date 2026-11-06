@use('App\Support\Dates')
@use('App\Support\Money')
@use('App\Services\Finance')
<x-layouts.site title="Finance" section="finance">
    <section class="hero">
        <div class="mute" style="font-size: 15px">Balance</div>
        <div class="finance-balance">{{ Money::format($finance->balance()) }}</div>
        @if ($finance->opening() !== 0)
            <p class="mute" style="margin: 8px 0 0">Includes {{ Money::format($finance->opening()) }} carried over from last season</p>
        @endif
        <div class="stats3 tight">
            <div><div class="stat-n">{{ Money::format($finance->received()) }}</div><div class="mute">Subs received</div></div>
            <div><div class="stat-n">{{ Money::format($finance->expenses()) }}</div><div class="mute">Expenses</div></div>
            <div><div class="owed">{{ Money::format($finance->owed()) }}</div><div class="mute">Subs owed</div></div>
        </div>
    </section>

    <section class="sec first">
        <h2 class="h2">Subs owed</h2>
        <p class="sub">What’s owed at {{ Money::format($finance->rate) }} a week, after what each person has paid</p>
        @forelse ($owing as $o)
            <div class="owing-row">
                <a class="nm" style="font-weight: 600" href="{{ route('players.show', [$o['player'], 'from' => 'finance']) }}">{{ $o['player']->name }}</a>
                <span class="owed">{{ Money::format($o['amount']) }}</span>
                <span class="mute">{{ Finance::weeksText($o['weeks'], true) }}</span>
            </div>
        @empty
            <p style="margin: 8px 0 0">Nobody owes anything.</p>
        @endforelse
        @auth
            <p class="mute"><a href="{{ route('admin.subs') }}">Record a payment</a></p>
        @endauth
    </section>

    <section class="sec">
        <h2 class="h2">Income from subs</h2>
        <p class="sub">Per league night, {{ Money::format($finance->rate) }} per player. Payments cover the oldest unpaid week first. Tap a week to see who’s covered.</p>
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

    <section class="sec">
        <h2 class="h2">Expenses</h2>
        <p class="sub">Money paid out this season</p>
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
            <p class="mute"><a href="{{ route('admin.expenses.index') }}">Add or edit expenses</a></p>
        @endauth
    </section>
</x-layouts.site>
