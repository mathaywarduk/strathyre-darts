<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Expense;
use App\Models\Season;
use App\Support\Money;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ExpenseController extends Controller
{
    public function index(): View|RedirectResponse
    {
        $season = Season::current();
        if (! $season) {
            return redirect()->route('admin.season.index')->withErrors(['season' => 'Start a season first.']);
        }

        return view('admin.expenses.index', ['expenses' => $season->expenses()->get()->reverse()->values()]);
    }

    public function store(Request $request): RedirectResponse
    {
        $season = Season::current();
        abort_unless($season, 404);
        Expense::create(['season_id' => $season->id, ...$this->validated($request)]);

        return redirect()->route('admin.expenses.index')->with('status', 'Expense added.');
    }

    public function edit(Expense $expense): View
    {
        return view('admin.expenses.edit', ['expense' => $expense]);
    }

    public function update(Request $request, Expense $expense): RedirectResponse
    {
        $expense->update($this->validated($request));

        return redirect()->route('admin.expenses.index')->with('status', 'Saved.');
    }

    public function destroy(Expense $expense): RedirectResponse
    {
        $expense->delete();

        return redirect()->route('admin.expenses.index')->with('status', 'Deleted.');
    }

    private function validated(Request $request): array
    {
        $data = $request->validate([
            'item' => ['required', 'string', 'max:150'],
            'cost' => ['required', 'regex:/^£?\d+(\.\d{1,2})?$/u'],
            'spent_on' => ['required', 'date_format:Y-m-d'],
        ], ['cost.regex' => 'Enter the cost as an amount, like 12.50.']);

        return ['item' => trim($data['item']), 'cost_pence' => Money::parse($data['cost']), 'spent_on' => $data['spent_on']];
    }
}
