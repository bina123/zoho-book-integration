<?php

namespace App\Support;

use App\Enums\BudgetCategory;

/**
 * Read-only presenter wrapping the raw ReportService payload + form input.
 * The Blade view should hold ZERO decision logic — it iterates this object
 * and renders strings.
 */
final class ReportPresenter
{
    public const DATE_DISPLAY_FORMAT = 'd/m/Y';

    public const MONTH_LABEL_FORMAT = 'F Y';

    public function __construct(
        public readonly ?array $report,
        public readonly string $monthA,
        public readonly string $monthB,
        public readonly string $monthALabel,
        public readonly string $monthBLabel,
        public readonly bool $connected,
        public readonly bool $hasOrganization,
        public readonly ?string $error = null,
    ) {}

    public function hasReport(): bool
    {
        return $this->report !== null;
    }

    public function columnA(): array
    {
        return $this->report['columns']['a'] ?? [];
    }

    public function columnB(): array
    {
        return $this->report['columns']['b'] ?? [];
    }

    public function sections(): array
    {
        return $this->report['sections'] ?? [];
    }

    public function budgetForColumn(string $key): array
    {
        return $this->report['budgets'][$key] ?? ['sales' => 0, 'cogs' => 0, 'net' => 0];
    }

    public function sectionBudget(string $sectionName, string $columnKey): float
    {
        $cat = BudgetCategory::fromSectionName($sectionName);
        if ($cat === null) {
            return 0.0;
        }

        return (float) ($this->budgetForColumn($columnKey)[$cat->value] ?? 0);
    }

    public function accountBudget(string $accountName, string $columnKey): float
    {
        $cat = BudgetCategory::fromAccountName($accountName);
        if ($cat === null) {
            return 0.0;
        }

        return (float) ($this->budgetForColumn($columnKey)[$cat->value] ?? 0);
    }

    public function netProfit(string $columnKey): float
    {
        return (float) ($this->report['net_profit'][$columnKey] ?? 0);
    }

    public function netBudget(string $columnKey): float
    {
        $budget = $this->budgetForColumn($columnKey);

        return (float) ($budget['sales'] ?? 0) - (float) ($budget['cogs'] ?? 0);
    }

    public static function format(float $value): string
    {
        return number_format($value, 0);
    }

    public static function variance(float $actual, float $budget): float
    {
        return $actual - $budget;
    }

    public static function varianceClass(float $variance): string
    {
        return $variance >= 0 ? 'pos' : 'neg';
    }
}
