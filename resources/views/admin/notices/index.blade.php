@use('App\Support\Dates')
<x-layouts.admin title="Updates">
    <h1 class="h2" style="margin-top: 16px">Updates</h1>
    <p class="sub">Announcements for the league, newest first, on the public Updates page. Expired updates stay here but are hidden from the public.</p>
    <p><a class="btn" href="{{ route('admin.notices.create') }}">Add an update</a></p>

    <section class="sec first">
        @forelse ($notices as $n)
            <div class="line">
                <span>
                    <span class="mute">{{ Dates::short($n->date) }} {{ $n->date->year }}</span> &nbsp;<strong>{{ $n->title }}</strong>
                    @if ($n->images_count)
                        <span class="mute" style="margin-left: 6px">{{ $n->images_count }} {{ \Illuminate\Support\Str::plural('picture', $n->images_count) }}</span>
                    @endif
                    @if ($n->isExpired())
                        <span class="tag" style="margin-left: 6px">Expired</span>
                    @elseif ($n->expires_on)
                        <span class="mute" style="margin-left: 6px">until {{ Dates::short($n->expires_on) }}</span>
                    @endif
                </span>
                <a class="ed" href="{{ route('admin.notices.edit', $n) }}" aria-label="Edit update: {{ $n->title }}">Edit</a>
            </div>
        @empty
            <p class="sub">No updates yet. The Updates page stays hidden until there is one.</p>
        @endforelse
        @if ($notices->contains(fn ($n) => ! $n->isExpired()))
            <p class="mute"><a href="{{ route('notices') }}">View the page</a></p>
        @endif
    </section>
</x-layouts.admin>
