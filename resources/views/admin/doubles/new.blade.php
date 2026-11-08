<x-layouts.admin title="Start a doubles tournament">
    <p><a class="back" href="{{ route('admin.doubles.index') }}">Back to doubles</a></p>
    <h1 class="h2">Start a doubles tournament</h1>
    <p class="sub">Tick who’s playing and add anyone else. When you start, everyone is put into teams of two at random and the knockout is drawn.</p>

    <form method="POST" action="{{ route('admin.doubles.store') }}" class="card stack" data-doubles>
        @csrf
        <div>
            <label class="label" for="name">Name</label>
            <input class="field" id="name" name="name" value="{{ $name }}" maxlength="80" required>
        </div>

        <fieldset class="plain-fieldset">
            <legend class="label">Defending champions (optional)</legend>
            <div class="cols">
                <div>
                    <label class="sr-only" for="champion1">Champion 1</label>
                    <input class="field" id="champion1" name="champions[]" value="{{ $champions[0] }}" list="league-names" placeholder="Champion 1" autocomplete="off" data-champion>
                </div>
                <div>
                    <label class="sr-only" for="champion2">Champion 2</label>
                    <input class="field" id="champion2" name="champions[]" value="{{ $champions[1] }}" list="league-names" placeholder="Champion 2" autocomplete="off" data-champion>
                </div>
            </div>
            <datalist id="league-names">
                @foreach ($players as $p)<option value="{{ $p->name }}">@endforeach
            </datalist>
        </fieldset>

        <fieldset class="plain-fieldset">
            <legend class="label">Players from the league</legend>
            <p class="hint" style="margin: 0 0 8px">
                One tap to add or remove. <button type="button" class="linkbtn" data-tick="all" style="min-height: 0">Tick everyone</button>
                · <button type="button" class="linkbtn" data-tick="none" style="min-height: 0">Clear</button>
            </p>
            <div class="picks">
                @forelse ($players as $p)
                    <label class="pick" data-name="{{ mb_strtolower($p->name) }}">
                        <input type="checkbox" name="players[]" value="{{ $p->id }}" @checked(in_array($p->id, $ticked, true)) data-player>
                        <span>{{ $p->name }}</span>
                        <span class="badge co" data-champ @unless (in_array(mb_strtolower($p->name), $championKeys, true)) hidden @endunless>Defending champion</span>
                    </label>
                @empty
                    <p class="hint">There are no league players yet. Add the other players below.</p>
                @endforelse
            </div>
        </fieldset>

        <div>
            <label class="label" for="extras">Other players</label>
            <input class="field" id="extras" name="extras" value="{{ $extras }}" placeholder="Mat, Kerry" autocomplete="off" data-extras>
            <p class="hint">Anyone who isn’t in the league, separated by commas.</p>
        </div>

        <p class="count" data-count aria-live="polite" style="margin: 0; font-weight: 600"></p>
        <button class="btn" type="submit">Start the tournament</button>
    </form>
</x-layouts.admin>
