@php($images = $notice->images)
@if ($images->count() === 1)
    {{-- One picture: shown large --}}
    @php($image = $images->first())
    <figure class="notice-photo">
        <a href="{{ $image->url() }}" data-gallery="notice-{{ $notice->id }}" data-caption="{{ $image->caption }}">
            <img src="{{ $image->url() }}" width="{{ $image->width }}" height="{{ $image->height }}" alt="{{ $image->caption }}" loading="lazy">
        </a>
        @if ($image->caption)<figcaption>{{ $image->caption }}</figcaption>@endif
    </figure>
@elseif ($images->count() > 1)
    {{-- Several: a gallery of thumbnails that opens large --}}
    <div class="gallery" role="list" aria-label="Pictures for {{ $notice->title }}">
        @foreach ($images as $image)
            <a role="listitem" href="{{ $image->url() }}" data-gallery="notice-{{ $notice->id }}" data-caption="{{ $image->caption }}">
                <img src="{{ $image->thumbUrl() }}" width="{{ $image->width }}" height="{{ $image->height }}" alt="{{ $image->caption ?: 'Picture '.$loop->iteration.' of '.$loop->count }}" loading="lazy">
            </a>
        @endforeach
    </div>
@endif
