<x-layouts.admin :title="$player->name">
    <p><a class="back" href="{{ route('admin.players.index') }}">Back to players</a></p>
    <h1 class="h2">{{ $player->name }}</h1>
    <p class="sub">Changing the name here hands this place to someone else: the results, points and subs stay with it.</p>

    <form method="POST" action="{{ route('admin.players.update', $player) }}" class="card stack" data-save-shortcut>
        @csrf @method('PUT')
        <div>
            <label class="label" for="name">Name</label>
            <input class="field" id="name" name="name" value="{{ old('name', $player->name) }}" required>
        </div>
        <div class="cols">
            <div>
                <label class="label" for="ranking">Ranking</label>
                <select class="field" id="ranking" name="ranking">@foreach (range(1, 5) as $n)<option value="{{ $n }}" @selected((int) old('ranking', $player->ranking) === $n)>{{ $n }}</option>@endforeach</select>
            </div>
            <div>
                <label class="label" for="gender">Gender</label>
                <select class="field" id="gender" name="gender">
                    <option value="M" @selected(old('gender', $player->gender) === 'M')>Man (M)</option>
                    <option value="W" @selected(old('gender', $player->gender) === 'W')>Woman (W)</option>
                </select>
            </div>
        </div>
        <button class="btn" type="submit">Save</button>
    </form>

    @unless ($hasFixtures)
        <form method="POST" action="{{ route('admin.players.destroy', $player) }}" class="card" style="margin-top: 16px">
            @csrf @method('DELETE')
            <p style="margin: 0 0 12px">This player has no fixtures, so they can be deleted.</p>
            <button class="btn danger" type="submit">Delete player</button>
        </form>
    @endunless
</x-layouts.admin>
