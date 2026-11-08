@php($editing = $notice->exists)
<x-layouts.admin :title="$editing ? 'Edit notice' : 'Add a notice'">
    <p><a class="back" href="{{ route('admin.notices.index') }}">Back to notices</a></p>
    <h1 class="h2">{{ $editing ? 'Edit notice' : 'Add a notice' }}</h1>

    <form method="POST" action="{{ $editing ? route('admin.notices.update', $notice) : route('admin.notices.store') }}" class="card stack" data-save-shortcut>
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
            <p class="hint">The last day the notice is shown on the public page. Leave empty to keep it up.</p>
        </div>
        <x-rich-editor name="content" label="Notice" :value="old('content', $notice->content)" />
        <button class="btn" type="submit">{{ $editing ? 'Save' : 'Add notice' }}</button>
    </form>

    @if ($editing)
        <form method="POST" action="{{ route('admin.notices.destroy', $notice) }}" class="card" style="margin-top: 16px" onsubmit="return confirm('Delete this notice?')">
            @csrf @method('DELETE')
            <button class="btn danger" type="submit">Delete notice</button>
        </form>
    @endif
</x-layouts.admin>
