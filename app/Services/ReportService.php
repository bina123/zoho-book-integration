<?php

namespace App\Services;

use App\Contracts\BudgetStore;
use App\Contracts\ZohoBooksClient;
use App\Services\Zoho\ProfitLossParser;
use Carbon\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

final class ReportService
{
    public const PNL_CACHE_TTL = 60; // seconds; short so new bills appear quickly

    public function __construct(
        protected ZohoBooksClient $booksService,
        protected BudgetStore $budgetRepository,
        protected ProfitLossParser $parser,
    ) {}

    /**
     * Build the side-by-side comparison report for two months.
     *
     * @param  string  $monthA  ISO month e.g. '2026-05'
     * @param  string  $monthB  ISO month e.g. '2026-04'
     */
    public function getComparison(string $monthA, string $monthB): array
    {
        $periodA = $this->monthBounds($monthA);
        $periodB = $this->monthBounds($monthB);

        $pnlA = $this->cachedProfitLoss($periodA['from'], $periodA['to']);
        $pnlB = $this->cachedProfitLoss($periodB['from'], $periodB['to']);

        $parsedA = $this->parser->parse($pnlA);
        $parsedB = $this->parser->parse($pnlB);

        $sectionsA = $this->indexSections($parsedA['sections']);
        $sectionsB = $this->indexSections($parsedB['sections']);

        $sectionOrder = $this->mergeOrder(array_keys($sectionsA), array_keys($sectionsB));

        $sections = [];
        foreach ($sectionOrder as $sectionKey) {
            $secA = $sectionsA[$sectionKey] ?? null;
            $secB = $sectionsB[$sectionKey] ?? null;

            $accountOrder = $this->mergeOrder(
                array_keys($secA['accounts'] ?? []),
                array_keys($secB['accounts'] ?? [])
            );

            $accounts = [];
            foreach ($accountOrder as $acctKey) {
                $a = $secA['accounts'][$acctKey] ?? null;
                $b = $secB['accounts'][$acctKey] ?? null;

                $accounts[] = [
                    'key' => $acctKey,
                    'name' => $a['name'] ?? $b['name'] ?? $acctKey,
                    'account_id_a' => $a['account_id'] ?? null,
                    'account_id_b' => $b['account_id'] ?? null,
                    'total_a' => (float) ($a['total'] ?? 0),
                    'total_b' => (float) ($b['total'] ?? 0),
                ];
            }

            $totalA = (float) ($secA['total'] ?? 0);
            $totalB = (float) ($secB['total'] ?? 0);

            // Skip sections that have no data and no accounts in either period (Page 19 fidelity).
            if ($totalA === 0.0 && $totalB === 0.0 && empty($accounts)) {
                continue;
            }

            $sections[] = [
                'key' => $sectionKey,
                'name' => $secA['name'] ?? $secB['name'] ?? $sectionKey,
                'accounts' => $accounts,
                'total_a' => $totalA,
                'total_b' => $totalB,
            ];
        }

        $netA = $this->resolveNetProfit($parsedA, $sectionsA);
        $netB = $this->resolveNetProfit($parsedB, $sectionsB);

        $budgets = $this->budgetRepository->getByMonths([$monthA, $monthB]) ?? collect();

        $budgetA = $budgets->get($monthA);
        $budgetB = $budgets->get($monthB);

        return [
            'columns' => [
                'a' => [
                    'month' => $monthA,
                    'label' => Carbon::parse($periodA['from'])->format('F Y'),
                    'from' => $periodA['from'],
                    'to' => $periodA['to'],
                ],
                'b' => [
                    'month' => $monthB,
                    'label' => Carbon::parse($periodB['from'])->format('F Y'),
                    'from' => $periodB['from'],
                    'to' => $periodB['to'],
                ],
            ],
            'sections' => $sections,
            'net_profit' => [
                'a' => $netA,
                'b' => $netB,
            ],
            'budgets' => [
                'a' => $this->budgetPayload($budgetA),
                'b' => $this->budgetPayload($budgetB),
            ],
        ];
    }

    public function getTransactions(string $accountId, string $month): array
    {
        $bounds = $this->monthBounds($month);
        $cacheKey = "zoho:txn:{$accountId}:{$month}";

        return Cache::remember($cacheKey, self::PNL_CACHE_TTL, function () use ($accountId, $bounds) {
            return $this->booksService->getAccountTransactions(
                $accountId,
                $bounds['from'],
                $bounds['to']
            );
        });
    }

    /**
     * Clear only Zoho-related cache entries — never call Cache::flush() in production,
     * it wipes sessions, rate-limit counters, and every other cached value.
     */
    public function clearCache(): void
    {
        $store = Cache::getStore();
        $prefix = method_exists($store, 'getPrefix') ? $store->getPrefix() : '';

        DB::table('cache')
            ->where('key', 'like', "{$prefix}zoho:%")
            ->delete();
    }

    protected function monthBounds(string $month): array
    {
        $start = Carbon::createFromFormat('Y-m', $month)->startOfMonth();
        $end = $start->copy()->endOfMonth();

        return [
            'from' => $start->toDateString(),
            'to' => $end->toDateString(),
        ];
    }

    protected function cachedProfitLoss(string $from, string $to): array
    {
        $key = "zoho:pnl:{$from}:{$to}";

        return Cache::remember(
            $key,
            self::PNL_CACHE_TTL,
            fn () => $this->booksService->getProfitLossReport($from, $to)
        );
    }

    /**
     * Index sections and their accounts by stable keys so we can merge two months.
     */
    protected function indexSections(array $parsed): array
    {
        $index = [];

        foreach ($parsed as $section) {
            $sectionKey = $this->keyOf($section['name']);
            $accounts = [];

            foreach ($section['accounts'] as $acct) {
                $acctKey = $this->keyOf($acct['name']);
                $accounts[$acctKey] = $acct;
            }

            $index[$sectionKey] = [
                'name' => $section['name'],
                'accounts' => $accounts,
                'total' => $section['total'],
            ];
        }

        return $index;
    }

    protected function keyOf(string $name): string
    {
        return strtolower(trim($name));
    }

    /**
     * Preserve order from the first list, append any new keys from the second.
     */
    protected function mergeOrder(array $first, array $second): array
    {
        $order = [];
        foreach ($first as $k) {
            $order[$k] = true;
        }
        foreach ($second as $k) {
            $order[$k] = true;
        }

        return array_keys($order);
    }

    /**
     * Prefer Zoho's own "Net Profit/Loss" total when present; otherwise compute it
     * from indexed sections (income minus expense/cost).
     */
    protected function resolveNetProfit(array $parsed, array $indexed): float
    {
        if (! empty($parsed['net_profit_found'])) {
            return (float) $parsed['net_profit'];
        }

        $income = 0.0;
        $expense = 0.0;

        foreach ($indexed as $section) {
            $name = strtolower($section['name']);
            if (str_contains($name, 'income')) {
                $income += (float) $section['total'];
            } elseif (str_contains($name, 'cost') || str_contains($name, 'expense')) {
                $expense += (float) $section['total'];
            }
        }

        return $income - $expense;
    }

    protected function budgetPayload(?\App\Models\Budget $budget): array
    {
        return [
            'sales' => (float) ($budget->sales_budget ?? 0),
            'cogs' => (float) ($budget->cogs_budget ?? 0),
            'net' => (float) (($budget->sales_budget ?? 0) - ($budget->cogs_budget ?? 0)),
        ];
    }
}
