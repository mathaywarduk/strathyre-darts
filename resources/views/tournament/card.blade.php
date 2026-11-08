@php
    /** One match: two teams, who won, and the checkout. For the admin it's a link to score it. */
    $clickable = $admin && $m->isReady();
    $tag = $clickable ? 'a' : 'div';
    $slots = [[$bracket->team($m->team_a_id), $m->team_a_id], [$bracket->team($m->team_b_id), $m->team_b_id]];
    $names = fn ($t) => $t ? implode(' and ', $t->names()) : 'to be decided';
@endphp
<{{ $tag }} class="br-card {{ $m->is_bye ? 'bye' : '' }} {{ $clickable ? 'link' : '' }} {{ $extra ?? '' }}" data-round="{{ $m->round }}" data-round-name="{{ $bracket->roundName($m->round) }}"
    @if ($clickable)
        href="{{ route('admin.doubles.match', [$m, 'back' => request()->getRequestUri()]) }}"
        aria-label="{{ $m->isPlayed() ? 'Edit the result of' : 'Add the result of' }} {{ $names($slots[0][0]) }} against {{ $names($slots[1][0]) }}"
    @endif
>
    @foreach ($slots as [$team, $id])
        <div class="br-team @if ($m->winner_team_id !== null && $id === $m->winner_team_id) win @elseif ($m->winner_team_id !== null && ! $m->is_bye) lose @endif">
            @if ($team)
                <span class="br-name">{{ $team->label() }}</span>
                @if ($team->defending)<span class="br-def" title="Defending champions" aria-label="Defending champions">★</span>@endif
            @elseif ($m->is_bye)
                <span class="br-name faint">Bye</span>
            @else
                <span class="br-name faint">To be decided</span>
            @endif
        </div>
    @endforeach
    @if ($m->checkout)
        <div class="br-meta">Checkout {{ $m->checkout }}@if ($clickable) <span class="act">· Edit</span>@endif</div>
    @elseif ($clickable)
        <div class="br-meta act">{{ $m->isPlayed() ? 'Edit result' : 'Add result' }}</div>
    @endif
</{{ $tag }}>
