<x-layouts.admin title="Players">
    <h1 class="h2" style="margin-top: 16px">Players</h1>

    @if ($preview)
        <div class="card">
            <h2 class="h2">Check before saving</h2>
            <p class="sub">Nothing is saved until you confirm.</p>
            <table class="plain-tbl">
                <thead><tr><th>Name</th><th>Ranking</th><th>Gender</th><th></th></tr></thead>
                <tbody>
                    @foreach ($preview as $row)
                        <tr>
                            <td>{{ $row['name'] }}</td><td>{{ $row['ranking'] }}</td><td>{{ $row['gender'] }}</td>
                            <td class="mute">{{ ['new' => 'New', 'update' => 'Will update', 'same' => 'No change'][$row['action']] }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
            <div class="actions" style="margin-top: 16px">
                <form method="POST" action="{{ route('admin.players.confirm') }}">@csrf<button class="btn" type="submit">Save {{ count($preview) }} players</button></form>
                <form method="POST" action="{{ route('admin.players.cancel') }}">@csrf<button class="btn ghost" type="submit">Cancel</button></form>
            </div>
        </div>
    @endif

    <section class="sec first">
        <p class="sub">{{ $players->count() }} {{ \Illuminate\Support\Str::plural('player', $players->count()) }}. Tap a name to edit, for example to hand a place to a replacement.</p>
        @foreach ($players as $p)
            <div class="line">
                <a class="nm" href="{{ route('admin.players.edit', $p) }}">{{ $p->name }}</a>
                <span class="mute">Ranking {{ $p->ranking }} · {{ $p->gender === 'W' ? 'Woman' : 'Man' }}</span>
            </div>
        @endforeach
    </section>

    <section class="sec">
        <h2 class="h2">Upload players</h2>
        <p class="sub">CSV with three columns: name, ranking (1 to 5, 5 is strongest) and gender (M or W). Players already in the list are updated by name. You’ll see a check before anything is saved.</p>
        <form method="POST" action="{{ route('admin.players.preview') }}" enctype="multipart/form-data" class="stack">
            @csrf
            <div>
                <label class="label" for="csv">CSV file</label>
                <input class="field" id="csv" name="csv" type="file" accept=".csv,text/csv,text/plain">
            </div>
            <div>
                <label class="label" for="text">Or paste the rows</label>
                <textarea class="field" id="text" name="text" placeholder="Name,Ranking,Gender&#10;Fiona M,4,W&#10;Callum F,5,M">{{ old('text') }}</textarea>
            </div>
            <button class="btn" type="submit">Check players</button>
        </form>
    </section>

    <section class="sec">
        <h2 class="h2">Add one player</h2>
        <form method="POST" action="{{ route('admin.players.store') }}" class="stack">
            @csrf
            <div class="cols">
                <div><label class="label" for="name">Name</label><input class="field" id="name" name="name" required></div>
                <div>
                    <label class="label" for="ranking">Ranking</label>
                    <select class="field" id="ranking" name="ranking">@foreach (range(1, 5) as $n)<option value="{{ $n }}">{{ $n }}</option>@endforeach</select>
                </div>
            </div>
            <div>
                <label class="label" for="gender">Gender</label>
                <select class="field" id="gender" name="gender"><option value="M">Man (M)</option><option value="W">Woman (W)</option></select>
            </div>
            <button class="btn ghost" type="submit">Add player</button>
        </form>
    </section>
</x-layouts.admin>
