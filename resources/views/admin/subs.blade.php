@use('App\Support\Dates')
@use('App\Support\Money')
@use('App\Services\Finance')
<x-layouts.admin title="Subs">
    <h1 class="h2" style="margin-top: 16px">Subs</h1>
    <p class="sub">Record whatever someone hands over, in any amount. It covers their oldest unpaid weeks first.</p>

    <form method="POST" action="{{ route('admin.subs.store') }}" class="card stack" id="record">
        @csrf
        <h2 class="h2">Record a payment</h2>
        <div>
            <label class="label" for="player_id">Player</label>
            <select class="field" id="player_id" name="player_id" required>
                <option value="">Choose a player</option>
                @foreach ($finance->league->players as $p)
                    @php
                        $owes = $finance->owedBy($p->id);
                        $ahead = $finance->creditOf($p->id);
                    @endphp
                    <option value="{{ $p->id }}" @selected($selected === $p->id)>
                        {{ $p->name }}@if ($owes > 0) · owes {{ Money::format($owes) }}@elseif ($ahead > 0) · {{ Money::format($ahead) }} ahead @endif
                    </option>
                @endforeach
            </select>
        </div>
        <div class="cols">
            <div>
                <label class="label" for="amount">Amount (£)</label>
                <input class="field" id="amount" name="amount" inputmode="decimal" value="{{ old('amount') }}" required>
            </div>
            <div>
                <label class="label" for="paid_on">Date</label>
                <input class="field" id="paid_on" name="paid_on" type="date" value="{{ old('paid_on', today()->toDateString()) }}" required>
            </div>
        </div>
        <button class="btn" type="submit">Record payment</button>
    </form>

    <section class="sec first">
        <h2 class="h2">Who owes</h2>
        <p class="sub">{{ Money::format($finance->owed()) }} owed by {{ $owing->count() }} {{ \Illuminate\Support\Str::plural('player', $owing->count()) }}, at {{ Money::format($finance->rate) }} a night for league nights up to today.</p>
        @forelse ($owing as $o)
            <div class="owing-row">
                <a class="nm" style="font-weight: 600" href="{{ route('admin.subs', ['player' => $o['player']->id]) }}#record">{{ $o['player']->name }}</a>
                <span class="owed">{{ Money::format($o['amount']) }}</span>
                <span class="mute">{{ Finance::weeksText($o['weeks'], true) }}</span>
            </div>
        @empty
            <p>Nobody owes anything.</p>
        @endforelse
        @if ($owing->isNotEmpty())
            <div style="margin-top: 24px">
                <label class="label" for="text">Copy to send</label>
                <textarea class="field" id="text" readonly style="min-height: 120px" onclick="this.select()">{{ $text }}</textarea>
            </div>
        @endif
    </section>

    <section class="sec">
        <h2 class="h2">Recent payments</h2>
        <p class="sub">Entered the wrong amount? Delete the payment and record it again.</p>
        @forelse ($payments as $pay)
            <form method="POST" action="{{ route('admin.subs.destroy', $pay) }}" class="line" onsubmit="return confirm('Delete this payment?')">
                @csrf @method('DELETE')
                <span><span class="mute">{{ Dates::short($pay->paid_on) }}</span> &nbsp;{{ $pay->player->name }}</span>
                <span class="right">
                    <strong>{{ Money::format($pay->amount_pence) }}</strong>
                    <button class="ed" type="submit" aria-label="Delete {{ Money::format($pay->amount_pence) }} payment from {{ $pay->player->name }}">Delete</button>
                </span>
            </form>
        @empty
            <p class="sub">No payments yet.</p>
        @endforelse
        <p class="mute"><a href="{{ route('finance.index') }}">See the public finance page</a></p>
    </section>
</x-layouts.admin>
