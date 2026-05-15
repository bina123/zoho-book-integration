<?php

namespace App\Http\Controllers;

use App\Contracts\BudgetStore;
use App\Http\Requests\UpdateBudgetRequest;
use Illuminate\Http\RedirectResponse;

final class BudgetController extends Controller
{
    public function __construct(protected BudgetStore $budgetStore) {}

    public function update(UpdateBudgetRequest $request): RedirectResponse
    {
        $validated = $request->validated();
        $this->budgetStore->upsert($validated);

        return redirect()->route('report.index', $request->only(['month_a', 'month_b']))
            ->with('status', "Budget for {$validated['month']} updated.");
    }
}
