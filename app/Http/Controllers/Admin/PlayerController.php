<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Fixture;
use App\Models\Player;
use App\Services\PlayerImport;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class PlayerController extends Controller
{
    public function index(): View
    {
        return view('admin.players.index', [
            'players' => Player::all()->sortBy(fn ($p) => mb_strtolower($p->name))->values(),
            'preview' => session('players_import'),
        ]);
    }

    /** Check the CSV and show what would change; nothing is saved yet. */
    public function preview(Request $request, PlayerImport $import): RedirectResponse|View
    {
        $request->validate(['csv' => ['nullable', 'file', 'max:512'], 'text' => ['nullable', 'string', 'max:50000']]);
        $text = $request->file('csv')?->get() ?? (string) $request->input('text');
        if (trim($text) === '') {
            return back()->withErrors(['text' => 'Choose a CSV file or paste the rows.']);
        }

        $result = $import->parse($text, Player::all());
        if ($result['errors']) {
            return back()->withInput(['text' => $text])->with('import_errors', $result['errors']);
        }

        session(['players_import' => $result['rows']]);

        return redirect()->route('admin.players.index');
    }

    public function confirm(): RedirectResponse
    {
        $rows = session('players_import');
        abort_unless($rows, 422, 'Nothing to save. Upload the file again.');

        DB::transaction(function () use ($rows) {
            foreach ($rows as $row) {
                $player = Player::whereRaw('lower(name) = ?', [mb_strtolower($row['name'])])->first();
                $data = ['name' => $row['name'], 'ranking' => $row['ranking'], 'gender' => $row['gender']];
                $player ? $player->update($data) : Player::create($data);
            }
        });
        session()->forget('players_import');

        return redirect()->route('admin.players.index')->with('status', 'Players saved.');
    }

    public function cancel(): RedirectResponse
    {
        session()->forget('players_import');

        return redirect()->route('admin.players.index');
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validated($request);
        Player::create($data);

        return redirect()->route('admin.players.index')->with('status', "Added {$data['name']}.");
    }

    public function edit(Player $player): View
    {
        return view('admin.players.edit', ['player' => $player, 'hasFixtures' => $this->hasFixtures($player)]);
    }

    public function update(Request $request, Player $player): RedirectResponse
    {
        $player->update($this->validated($request, $player));

        return redirect()->route('admin.players.index')->with('status', 'Saved.');
    }

    public function destroy(Player $player): RedirectResponse
    {
        if ($this->hasFixtures($player)) {
            return back()->withErrors(['player' => 'This player has fixtures. Edit their name to hand the place to a replacement instead.']);
        }
        $player->delete();

        return redirect()->route('admin.players.index')->with('status', 'Deleted.');
    }

    private function hasFixtures(Player $player): bool
    {
        return Fixture::where('player1_id', $player->id)->orWhere('player2_id', $player->id)->exists();
    }

    private function validated(Request $request, ?Player $player = null): array
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'ranking' => ['required', 'integer', 'between:1,5'],
            'gender' => ['required', Rule::in(['M', 'W'])],
        ]);
        $data['name'] = preg_replace('/\s+/', ' ', trim($data['name']));

        $clash = Player::whereRaw('lower(name) = ?', [mb_strtolower($data['name'])])
            ->when($player, fn ($q) => $q->where('id', '!=', $player->id))->exists();
        if ($clash) {
            back()->withInput()->withErrors(['name' => 'Another player already has that name.'])->throwResponse();
        }

        return $data;
    }
}
