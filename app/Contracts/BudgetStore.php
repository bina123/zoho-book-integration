<?php

namespace App\Contracts;

use App\Models\Budget;
use Illuminate\Support\Collection;

interface BudgetStore
{
    public function getByMonth(string $month): ?Budget;

    public function getByMonths(array $months): Collection;

    public function upsert(array $budgetData): Budget;
}
