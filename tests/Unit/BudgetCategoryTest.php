<?php

namespace Tests\Unit;

use App\Enums\BudgetCategory;
use PHPUnit\Framework\TestCase;

final class BudgetCategoryTest extends TestCase
{
    public function test_resolves_sales_from_operating_income_section(): void
    {
        $this->assertSame(BudgetCategory::Sales, BudgetCategory::fromSectionName('Operating Income'));
    }

    public function test_resolves_cogs_from_cost_of_goods_sold_section(): void
    {
        $this->assertSame(BudgetCategory::CostOfGoodsSold, BudgetCategory::fromSectionName('Cost of Goods Sold'));
    }

    public function test_returns_null_for_unknown_section(): void
    {
        $this->assertNull(BudgetCategory::fromSectionName('Operating Expense'));
        $this->assertNull(BudgetCategory::fromSectionName(null));
        $this->assertNull(BudgetCategory::fromSectionName(''));
    }

    public function test_resolves_sales_from_sales_account_name(): void
    {
        $this->assertSame(BudgetCategory::Sales, BudgetCategory::fromAccountName('Sales'));
        $this->assertSame(BudgetCategory::Sales, BudgetCategory::fromAccountName('Domestic Sales'));
    }

    public function test_resolves_cogs_from_account_name(): void
    {
        $this->assertSame(BudgetCategory::CostOfGoodsSold, BudgetCategory::fromAccountName('Cost of Goods Sold'));
    }

    public function test_column_derives_database_column_name(): void
    {
        $this->assertSame('sales_budget', BudgetCategory::Sales->column());
        $this->assertSame('cogs_budget', BudgetCategory::CostOfGoodsSold->column());
    }
}
