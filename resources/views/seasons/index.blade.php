@use('App\Support\Dates')
<x-layouts.site title="Past seasons">
    <section class="hero">
        <h1 class="page-title">Past seasons</h1>
    </section>
    <section class="sec first">
        @forelse ($seasons as $season)
            <div class="line">
                <a class="nm" href="{{ route('seasons.show', $season) }}" style="font-weight: 600">Season {{ $season->label }}</a>
                <span class="mute">Started {{ Dates::short($season->starts_on) }} {{ $season->starts_on->year }}</span>
            </div>
        @empty
            <p class="sub">No past seasons yet.</p>
        @endforelse
    </section>
</x-layouts.site>
