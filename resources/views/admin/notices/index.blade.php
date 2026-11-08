@use('App\Support\Dates')
<x-layouts.admin title="Notices">
    <h1 class="h2" style="margin-top: 16px">Notices</h1>
    <p class="sub">Announcements for the league, newest first, on the public Notices page. Expired notices stay here but are hidden from the public.</p>
    <p><a class="btn" href="{{ route('admin.notices.create') }}">Add a notice</a></p>

    <section class="sec first">
        @forelse ($notices as $n)
            <div class="line">
                <span>
                    <span class="mute">{{ Dates::short($n->date) }} {{ $n->date->year }}</span> &nbsp;<strong>{{ $n->title }}</strong>
                    @if ($n->isExpired())
                        <span class="tag" style="margin-left: 6px">Expired</span>
                    @elseif ($n->expires_on)
                        <span class="mute" style="margin-left: 6px">until {{ Dates::short($n->expires_on) }}</span>
                    @endif
                </span>
                <a class="ed" href="{{ route('admin.notices.edit', $n) }}" aria-label="Edit notice: {{ $n->title }}">Edit</a>
            </div>
        @empty
            <p class="sub">No notices yet. The Notices page stays hidden until there is one.</p>
        @endforelse
        @if ($notices->contains(fn ($n) => ! $n->isExpired()))
            <p class="mute"><a href="{{ route('notices') }}">View the page</a></p>
        @endif
    </section>
</x-layouts.admin>
