@use('App\Support\Money')
@use('App\Services\Finance')
<x-layouts.admin title="Subs owed">
    <h1 class="h2" style="margin-top: 16px">Subs owed</h1>
    <p class="sub">Unpaid subs at {{ Money::format($finance->rate) }} a night, for league nights up to today.</p>

    <div class="card">
        <p class="big" style="font-size: 56px; margin: 0"><span class="owed-text">{{ Money::format($finance->owed()) }}</span></p>
        <p class="mute" style="margin: 4px 0 0">{{ $owing->count() }} {{ \Illuminate\Support\Str::plural('player', $owing->count()) }} owing</p>
    </div>

    @forelse ($owing as $o)
        <div class="owing-row">
            <a class="nm" style="font-weight: 600" href="{{ route('players.show', [$o['player'], 'from' => 'finance']) }}">{{ $o['player']->name }}</a>
            <span class="owed">{{ Money::format($o['amount']) }}</span>
            <span class="mute">{{ Finance::weeksText($o['weeks'], true) }}</span>
        </div>
    @empty
        <p>Nobody owes anything.</p>
    @endforelse

    @if ($owing->isNotEmpty())
        <section class="sec">
            <label class="label" for="text">Copy to send</label>
            <textarea class="field" id="text" readonly style="min-height: 120px" onclick="this.select()">{{ $text }}</textarea>
        </section>
    @endif
    <p class="mute"><a href="{{ route('finance.index') }}">See the full finance page</a></p>
</x-layouts.admin>
