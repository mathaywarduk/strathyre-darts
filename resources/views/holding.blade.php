<x-layouts.site title="Coming soon">
    <section class="hero">
        <div class="hero-big">Coming soon</div>
        <p>The league isn’t open yet. Check back soon for the table, fixtures and results.</p>
    </section>
    @auth
        @if (count($missing))
            <section class="sec first">
                <h2 class="h2">Still to do</h2>
                <ul class="plain">
                    @foreach ($missing as $item)
                        <li class="line">{{ $item }}</li>
                    @endforeach
                </ul>
                <p class="mute"><a href="{{ route('admin.dashboard') }}">Open the admin</a></p>
            </section>
        @endif
    @endauth
    @if ($hasArchive)
        <p class="mute sec"><a href="{{ route('seasons.index') }}">Past seasons</a></p>
    @endif
</x-layouts.site>
