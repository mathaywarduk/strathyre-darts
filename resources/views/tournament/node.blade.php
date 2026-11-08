@php
    /** A match and, to its left (or right), the matches that fed it. */
    $kids = $bracket->feeders($m);
@endphp
<div class="br-node {{ $side }}">
    @if ($kids)
        <div class="br-kids">
            @foreach ($kids as $kid)
                @include('tournament.node', ['m' => $kid, 'bracket' => $bracket, 'admin' => $admin, 'side' => $side])
            @endforeach
        </div>
    @endif
    @include('tournament.card', ['m' => $m, 'bracket' => $bracket, 'admin' => $admin])
</div>
