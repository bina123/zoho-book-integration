<?php

namespace App\Repositories;

use App\Contracts\BudgetStore;
use App\Models\Budget;
use Illuminate\Support\Collection;

final class BudgetRepository implements BudgetStore
{
    public function getByMonth(string $month): ?Budget
    {
        return Budget::where('month', $month)->first();
    }

    /**
     * @return Collection<string, Budget>
     */
    public function getByMonths(array $months): Collection
    {
        return Budget::whereIn('month', $months)->get()->keyBy('month');
    }

    public function upsert(array $budgetData): Budget
    {
        return Budget::updateOrCreate(
            ['month' => $budgetData['month']],
            [
                'sales_budget' => $budgetData['sales_budget'],
                'cogs_budget' => $budgetData['cogs_budget'],
            ]
        );
    }
}
