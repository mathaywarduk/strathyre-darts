<x-layouts.site :title="$tournament->name" section="doubles">
    <section class="hero" style="padding-top: 16px">
        <h1 class="page-title" style="font-size: 56px">{{ $tournament->name }}</h1>
        <p class="mute" style="margin: 8px 0 0">{{ $bracket->teams->count() }} teams · {{ $bracket->played() }} of {{ $bracket->toPlay() }} matches played</p>
    </section>

    <section style="padding-top: 24px">
        @include('tournament.bracket', ['bracket' => $bracket, 'admin' => auth()->check()])
    </section>
</x-layouts.site>
