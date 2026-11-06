@use('App\Support\Dates')
@php
    $catchText = $catchUps->count() === 0 ? 'no catch-ups to play' : $catchUps->count().' '.\Illuminate\Support\Str::plural('catch-up', $catchUps->count()).' to play';
    $progress = $league->weeksPlayed().' of '.$league->totalWeeks().' weeks played, '.$catchText;
@endphp
<x-layouts.site>
    <section class="hero">
        @if ($night)
            <div class="hero-big">Week {{ $night->week }}</div>
            <p>{{ Dates::long($night->date) }}
                @if ($pending)
                    <span class="tag" style="margin-left: 8px; vertical-align: middle">Results pending</span>
                @endif
            </p>
        @else
            <div class="hero-big descend">Season complete</div>
        @endif
        <p class="mute">{{ $progress }}</p>
    </section>

    <section class="sec first">
        <h2 class="h2">League table</h2>
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
                    @foreach ($table as $r)
                        @php
                            $id = $r['player']->id;
                            $isW = $leaders['W'] === $id;
                            $isM = $leaders['M'] === $id;
                        @endphp
                        <tr @class(['lead-w' => $isW, 'lead-m' => $isM])>
                            <td style="color: var(--mute)">{{ $r['pos'] }}{{ $r['tied'] ? '=' : '' }}</td>
                            <td class="l">
                                <div class="namecell">
                                    <a class="nm" href="{{ route('players.show', $r['player']) }}">{{ $r['player']->name }}</a>
                                    @if ($isW)<span class="badge w">Women’s leader</span>@endif
                                    @if ($isM)<span class="badge m">Men’s leader</span>@endif
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

    @if ($bestCheckout)
        <section class="sec">
            <div class="mute">Highest checkout</div>
            <div class="big" style="font-size: 72px; margin-top: 4px">{{ $bestCheckout['checkout'] }}</div>
            <div style="margin-top: 6px">
                @foreach ($bestCheckout['fixtures'] as $f)
                    @if (! $loop->first)<span class="mute">·</span> @endif<a href="{{ route('players.show', $f->result->winner_id) }}">{{ $league->name($f->result->winner_id) }}</a> <span class="mute">(week {{ $f->night->week }})</span>
                @endforeach
            </div>
        </section>
    @endif

    @if ($night)
        <section class="sec">
            <h2 class="h2">Next week</h2>
            <p class="sub">Week {{ $night->week }}, {{ Dates::long($night->date) }}</p>
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
        <h2 class="h2">Last week’s results</h2>
        @if ($last)
            <p class="sub">Week {{ $last->week }}, {{ Dates::long($last->date) }}</p>
            @forelse ($lastResults as $f)
                @php($res = $f->result)
                <div class="line">
                    <span>
                        <span style="font-weight: 700">{{ $league->name($res->winner_id) }}</span>
                        <span class="v">beat</span> {{ $league->name($res->loserId()) }}
                        @if ($f->night->week !== $last->week)
                            <span class="mute">(week {{ $f->night->week }})</span>
                        @endif
                    </span>
                    <span class="right">
                        <span><span class="mute">Checkout:</span> <strong>{{ $res->checkout }}</strong></span>
                        @auth
                            <a class="ed" href="{{ route('admin.results.edit', [$res, 'back' => request()->getRequestUri()]) }}" aria-label="Edit result: {{ $league->name($res->winner_id) }} v {{ $league->name($res->loserId()) }}">Edit</a>
                        @endauth
                    </span>
                </div>
            @empty
                <p style="margin: 8px 0 0">{{ $last->no_results ? 'No matches were played. They’re all catch-ups now.' : 'No results entered.' }}</p>
            @endforelse
        @else
            <p class="sub">No results yet.</p>
        @endif
    </section>

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

    @if ($hasArchive)
        <p class="mute sec"><a href="{{ route('seasons.index') }}">Past seasons</a></p>
    @endif
</x-layouts.site>
