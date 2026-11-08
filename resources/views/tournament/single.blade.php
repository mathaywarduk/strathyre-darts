@php
    /** One side: every round left to right, the final at the right with the trophy beside it. Arrows move between rounds. */
    $final = $bracket->final();
    $champions = $bracket->champions();
@endphp
<div class="bs {{ ($always ?? false) ? 'always' : '' }}" data-bracket-single>
    <div class="bs-nav">
        <button type="button" class="wn-btn" data-bs="prev" aria-label="Previous round">‹</button>
        <div class="bs-title" data-bs-title aria-live="polite">{{ $bracket->roundName(1) }}</div>
        <button type="button" class="wn-btn" data-bs="next" aria-label="Next round">›</button>
    </div>
    <div class="bs-scroll" data-bs-scroll tabindex="0" role="region" aria-label="Knockout bracket. Scroll sideways to see the later rounds.">
        <div class="bs-tree">
            @include('tournament.node', ['m' => $final, 'bracket' => $bracket, 'admin' => $admin, 'side' => 'left'])
            <div class="bs-end" data-round="end" data-round-name="{{ $champions ? 'Champions' : 'Winner' }}">
                <div class="br-trophy">@include('tournament.trophy')</div>
                <div class="br-winner {{ $champions ? 'done' : '' }}">
                    <div class="br-winner-box">
                        @if ($champions)<span class="br-name">{{ $champions->label() }}</span>@endif
                    </div>
                    <div class="br-winner-label">{{ $champions ? 'Champions' : 'Winner' }}</div>
                </div>
            </div>
        </div>
    </div>
</div>
