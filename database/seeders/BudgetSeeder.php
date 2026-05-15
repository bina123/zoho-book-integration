<?php

namespace Database\Seeders;

use App\Models\Budget;
use Illuminate\Database\Seeder;

class BudgetSeeder extends Seeder
{
    public function run(): void
    {
        // Defaults match the Profit & Loss target on Page 19 of the practical brief.
        $rows = [
            ['month' => '2026-04', 'sales_budget' => 115000, 'cogs_budget' => 80000],
            ['month' => '2026-05', 'sales_budget' => 225000, 'cogs_budget' => 50000],
        ];

        foreach ($rows as $row) {
            Budget::updateOrCreate(
                ['month' => $row['month']],
                [
                    'sales_budget' => $row['sales_budget'],
                    'cogs_budget' => $row['cogs_budget'],
                ]
            );
        }
    }
}
