<?php

namespace App\Enums;

/**
 * Budget category keys. The string value is the column name on `budgets`
 * (suffixed with `_budget`) so callers can derive the column dynamically.
 */
enum BudgetCategory: string
{
    case Sales = 'sales';
    case CostOfGoodsSold = 'cogs';

    public function column(): string
    {
        return "{$this->value}_budget";
    }

    /**
     * Resolve a budget category from a Zoho section or account name.
     */
    public static function fromSectionName(?string $name): ?self
    {
        $name = strtolower((string) $name);

        if (str_contains($name, 'income') && str_contains($name, 'operating')) {
            return self::Sales;
        }

        if (str_contains($name, 'cost of goods') || str_contains($name, 'cogs')) {
            return self::CostOfGoodsSold;
        }

        return null;
    }

    public static function fromAccountName(?string $name): ?self
    {
        $name = strtolower((string) $name);

        if (str_contains($name, 'sales')) {
            return self::Sales;
        }

        if (str_contains($name, 'cost of goods') || str_contains($name, 'cogs')) {
            return self::CostOfGoodsSold;
        }

        return null;
    }
}
