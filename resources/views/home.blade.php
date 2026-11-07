@use('App\Support\Dates')
@php
    $catchText = $catchUps->count() === 0 ? 'no catch-ups to play' : $catchUps->count().' '.\Illuminate\Support\Str::plural('catch-up', $catchUps->count()).' to play';
    $progress = $night
        ? $league->weeksPlayed().' of '.$league->totalWeeks().' weeks played, '.$catchText
        : 'All '.$league->totalWeeks().' weeks played, '.$catchText;
@endphp
<x-layouts.site>
    <div class="split">
    <div class="split-main">
    <section style="padding-top: 16px">
        <h2 class="h2">League table</h2>
        <p class="sub">{{ $progress }}</p>
        <div style="overflow-x: auto">
            <table class="tbl">
                <thead>
                    <tr>
                        <th scope="col" style="width: 28px">Pos</th>
                        <th scope="col" class="l">Name</th>
                        <th scope="col"><abbr title="Matches played">P</abbr></th>
                        <th scope="col">Pts</th>
                    </tr>
                </thead>
                <tbody>
                    @php
                        $started = $table->contains(fn ($r) => $r['played'] > 0);
                    @endphp
                    @foreach ($table as $r)
                        @php
                            $id = $r['player']->id;
                            $isW = $leaders['W'] === $id;
                            $isM = $leaders['M'] === $id;
                        @endphp
                        {{-- The top three stand out once play has started --}}
                        <tr @class(['lead-w' => $isW, 'lead-m' => $isM, 'p1' => $started && $r['pos'] === 1, 'p2' => $started && $r['pos'] === 2, 'p3' => $started && $r['pos'] === 3])>
                            <td style="color: var(--mute)">{{ $r['pos'] }}{{ $r['tied'] ? '=' : '' }}</td>
                            <td class="l">
                                <div class="namecell">
                                    <a class="nm" href="{{ route('players.show', $r['player']) }}">{{ $r['player']->name }}</a>
                                    {{-- On a phone these shrink to initials so the name keeps its room --}}
                                    @if ($isW)<span class="badge w" title="Women’s leader"><span class="b-long">Women’s leader</span><span class="b-short" aria-hidden="true">WL</span></span>@endif
                                    @if ($isM)<span class="badge m" title="Men’s leader"><span class="b-long">Men’s leader</span><span class="b-short" aria-hidden="true">ML</span></span>@endif
                                    @if (in_array($id, $bestCheckoutIds, true))<span class="badge co" title="Highest checkout"><span class="b-long">Highest checkout</span><span class="b-short" aria-hidden="true">HC</span></span>@endif
                                </div>
                            </td>
                            <td>{{ $r['played'] }}</td>
                            <td style="font-weight: 700">{{ $r['pts'] }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
        @if ($league->isComplete() && $table->contains('tied', true))
            <p class="mute" style="margin: 12px 0 0">Players on a shared position are level after head-to-head and will play off.</p>
        @endif
    </section>

    </div>
    <div class="split-side">
    @if ($bestCheckout)
        <section class="sec">
            <div class="checkout-card">
                <div class="cc-circle">{{ $bestCheckout['checkout'] }}</div>
                <div class="cc-text">
                    <div class="cc-label">Highest checkout</div>
                    @foreach ($bestCheckout['fixtures'] as $f)
                        <div class="cc-who"><a href="{{ route('players.show', $f->result->winner_id) }}">{{ $league->name($f->result->winner_id) }}</a> <span class="wk">(week {{ $f->night->week }})</span></div>
                    @endforeach
                </div>
            </div>
        </section>
    @endif

    @if ($night)
        <section class="sec">
            <h2 class="h2">Week {{ $night->week }}</h2>
            <p class="sub">{{ Dates::long($night->date) }}
                @if ($pending)
                    <span class="tag" style="margin-left: 8px; vertical-align: middle">Results pending</span>
                @endif
            </p>
            @foreach ($nextFixtures as $f)
                <div class="line">
                    <span>{{ $league->name($f->player1_id) }} <span class="v">v</span> {{ $league->name($f->player2_id) }}</span>
                    @if ($f->result)<span class="mute">Already played</span>@endif
                </div>
            @endforeach
            @if ($byes->isNotEmpty())
                <div class="line"><span class="mute">Bye: {{ $byes->pluck('name')->implode(', ') }}</span></div>
            @endif
        </section>
    @endif

    <section class="sec">
        <h2 class="h2">Catch-up fixtures</h2>
        <p class="sub">Matches postponed or missed, still to be played</p>
        @forelse ($catchUps as $f)
            <div class="line">
                <span>{{ $league->name($f->player1_id) }} <span class="v">v</span> {{ $league->name($f->player2_id) }}</span>
                <span class="mute" style="white-space: nowrap">From week {{ $f->night->week }}</span>
            </div>
        @empty
            <p style="margin: 8px 0 0">No catch-ups to play.</p>
        @endforelse
    </section>

    </div>
    </div>

    @if ($hasArchive)
        <p class="mute sec"><a href="{{ route('seasons.index') }}">Past seasons</a></p>
    @endif
</x-layouts.site>
