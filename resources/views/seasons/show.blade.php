@use('App\Support\Dates')
<x-layouts.site :title="'Season '.$season->label">
    <div style="padding-top: 8px"><a class="back" href="{{ route('seasons.index') }}">Back to past seasons</a></div>
    <section class="hero" style="padding-top: 16px">
        <h1 class="page-title">Season {{ $season->label }}</h1>
        <p class="mute">Final table and results</p>
    </section>

    <div class="split even"><div class="split-main">
    <section class="sec first">
        <h2 class="h2">Final table</h2>
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
                    @foreach ($snapshot['table'] as $r)
                        <tr>
                            <td style="color: var(--mute)">{{ $r['pos'] }}{{ ($r['tied'] ?? false) ? '=' : '' }}</td>
                            <td class="l" style="font-weight: 500">{{ $r['name'] }}</td>
                            <td>{{ $r['played'] }}</td>
                            <td style="font-weight: 700">{{ $r['pts'] }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </section>

    @php
        $best = null;
        foreach ($snapshot['weeks'] as $w) {
            foreach ($w['results'] as $r) {
                $c = $r['checkout'] ?? null;
                if ($c !== null && ($best === null || $c > $best['checkout'])) {
                    $best = ['checkout' => $c, 'names' => [$r['winner'].' (week '.$w['week'].')']];
                } elseif ($c !== null && $c === $best['checkout']) {
                    $best['names'][] = $r['winner'].' (week '.$w['week'].')';
                }
            }
        }
    @endphp
    @if ($best)
        <section class="sec">
            <div class="checkout-card">
                <div class="cc-circle">{{ $best['checkout'] }}</div>
                <div class="cc-text">
                    <div class="cc-label">Highest checkout</div>
                    @foreach ($best['names'] as $who)
                        <div class="cc-who">{{ $who }}</div>
                    @endforeach
                </div>
            </div>
        </section>
    @endif

    </div><div class="split-side">
    @foreach (array_reverse($snapshot['weeks']) as $week)
        @if (count($week['results']) || count($week['unplayed']))
            <section class="sec">
                <h2 class="h2">Week {{ $week['week'] }}</h2>
                <p class="sub">{{ Dates::long(\Carbon\CarbonImmutable::parse($week['date'])) }}</p>
                @foreach ($week['results'] as $res)
                    <div class="line">
                        <span><span style="font-weight: 700">{{ $res['winner'] }}</span> <span class="v">beat</span> {{ $res['loser'] }}</span>
                        @if (isset($res['checkout']))<span><span class="mute">Checkout:</span> <strong>{{ $res['checkout'] }}</strong></span>@endif
                    </div>
                @endforeach
                @foreach ($week['unplayed'] as $pair)
                    <div class="line"><span class="mute">{{ $pair[0] }} v {{ $pair[1] }}: not played</span></div>
                @endforeach
            </section>
        @endif
    @endforeach
    </div></div>
</x-layouts.site>
