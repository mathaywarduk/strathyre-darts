@php($editing = $notice->exists)
<x-layouts.admin :title="$editing ? 'Edit update' : 'Add an update'">
    <p><a class="back" href="{{ route('admin.notices.index') }}">Back to updates</a></p>
    <h1 class="h2">{{ $editing ? 'Edit update' : 'Add an update' }}</h1>

    <form method="POST" action="{{ $editing ? route('admin.notices.update', $notice) : route('admin.notices.store') }}" class="card stack" data-save-shortcut enctype="multipart/form-data">
        @csrf
        @if ($editing) @method('PUT') @endif
        <div class="cols">
            <div>
                <label class="label" for="title">Title</label>
                <input class="field" id="title" name="title" value="{{ old('title', $notice->title) }}" maxlength="150" required>
            </div>
            <div>
                <label class="label" for="date">Date</label>
                <input class="field" id="date" name="date" type="date" value="{{ old('date', $notice->date?->toDateString()) }}" required>
            </div>
        </div>
        <div>
            <label class="label" for="expires_on">Hide after (optional)</label>
            <input class="field" id="expires_on" name="expires_on" type="date" value="{{ old('expires_on', $notice->expires_on?->toDateString()) }}" style="max-width: 260px">
            <p class="hint">The last day the update is shown on the public page. Leave empty to keep it up.</p>
        </div>
        <x-rich-editor name="content" label="Update" :value="old('content', $notice->content)" />

        <fieldset class="plain-fieldset pictures-field">
            <legend class="label">Pictures (optional)</legend>
            <p class="hint" style="margin: 0 0 12px">One picture shows large. Two or more make a gallery that people can tap to enlarge. JPG, PNG, WebP or GIF, up to {{ config('league.images_per_notice') }}. They’re resized for you.</p>

            @foreach ($notice->images as $image)
                <div class="photo-row">
                    <img src="{{ $image->thumbUrl() }}" alt="" width="96" height="72" loading="lazy">
                    <div class="photo-fields">
                        <label class="label" for="caption-{{ $image->id }}">Caption</label>
                        <input class="field" id="caption-{{ $image->id }}" name="captions[{{ $image->id }}]" value="{{ old('captions.'.$image->id, $image->caption) }}" maxlength="200">
                        <label class="check"><input type="checkbox" name="remove[]" value="{{ $image->id }}" @checked(in_array($image->id, array_map('intval', (array) old('remove', [])), true))> Remove this picture</label>
                    </div>
                </div>
            @endforeach

            <div>
                <label class="label" for="images">{{ $notice->images->isEmpty() ? 'Add pictures' : 'Add more pictures' }}</label>
                <input class="field" id="images" name="images[]" type="file" accept="image/jpeg,image/png,image/webp,image/gif" multiple>
                <p class="hint">Choose as many as you like at once. You can add captions after saving.</p>
            </div>
        </fieldset>
        <button class="btn" type="submit">{{ $editing ? 'Save' : 'Add update' }}</button>
    </form>

    @if ($editing)
        <form method="POST" action="{{ route('admin.notices.destroy', $notice) }}" class="card" style="margin-top: 16px" onsubmit="return confirm('Delete this update?')">
            @csrf @method('DELETE')
            <button class="btn danger" type="submit">Delete update</button>
        </form>
    @endif
</x-layouts.admin>
