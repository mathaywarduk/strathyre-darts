@php
    /**
     * The knockout. Wide screens show it two-sided, meeting at the trophy; phones show one side with arrows
     * between rounds. Pass single=true (the admin does) to always use the one-sided view.
     */
    $final = $bracket->final();
    $left = $final ? $bracket->match($final->round - 1, 1) : null;
    $right = $final ? $bracket->match($final->round - 1, 2) : null;
    $champions = $bracket->champions();
    $single = $single ?? false;
@endphp
@if ($final)
    @unless ($single)
        <div class="br-board r{{ $bracket->rounds }}" role="group" aria-label="Knockout bracket">
            <div class="br-half left">
                @if ($left)
                    @include('tournament.node', ['m' => $left, 'bracket' => $bracket, 'admin' => $admin, 'side' => 'left'])
                @endif
            </div>

            <div class="br-centre">
                @include('tournament.card', ['m' => $final, 'bracket' => $bracket, 'admin' => $admin, 'extra' => 'br-final'])
                <div class="br-below">
                    <div class="br-trophy">@include('tournament.trophy')</div>
                    <div class="br-winner {{ $champions ? 'done' : '' }}">
                        <div class="br-winner-box">
                            @if ($champions)<span class="br-name">{{ $champions->label() }}</span>@endif
                        </div>
                        <div class="br-winner-label">{{ $champions ? 'Champions' : 'Winner' }}</div>
                    </div>
                </div>
            </div>

            <div class="br-half right">
                @if ($right)
                    @include('tournament.node', ['m' => $right, 'bracket' => $bracket, 'admin' => $admin, 'side' => 'right'])
                @endif
            </div>
        </div>
    @endunless

    @include('tournament.single', ['bracket' => $bracket, 'admin' => $admin, 'always' => $single])
    <script src="{{ asset('js/bracket.js') }}?v={{ filemtime(public_path('js/bracket.js')) }}" defer></script>
@endif
