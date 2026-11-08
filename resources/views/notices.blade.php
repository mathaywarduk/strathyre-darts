@use('App\Support\Dates')
<x-layouts.site title="Notices" section="notices">
    <section class="hero" style="padding-top: 16px">
        <h1 class="page-title">Notices</h1>
    </section>
    <div class="narrow">
        @foreach ($notices as $n)
            <article class="sec {{ $loop->first ? 'first' : '' }}" id="notice-{{ $n->id }}">
                <h2 class="h2">{{ $n->title }}</h2>
                <p class="sub">{{ Dates::dayMonth($n->date) }} {{ $n->date->year }}</p>
                <div class="rich">{!! $n->content !!}</div>
            </article>
        @endforeach
    </div>
</x-layouts.site>
