<?php

namespace App\Services\Zoho;

/**
 * Parses Zoho Books' /reports/profitandloss response into a flat structure.
 *
 * Zoho returns a 3-level tree:
 *   - top level: subtotal wrappers ("Gross Profit", "Operating Profit", "Net Profit/Loss")
 *   - mid level: category sections (identified by `total_label`, e.g. "Operating Income")
 *   - leaf level: real accounts with `account_id`
 *
 * We collect every category section we encounter and recursively gather its
 * leaf accounts. The "Net Profit/Loss" top-level total is captured separately.
 */
final class ProfitLossParser
{
    /**
     * @return array{sections: array<int, array{name: string, accounts: array, total: float}>, net_profit: float, net_profit_found: bool}
     */
    public function parse(array $response): array
    {
        $top = $response['profit_and_loss']
            ?? $response['profitandloss']
            ?? $response['data']['profit_and_loss']
            ?? [];

        $sections = [];
        $netProfit = 0.0;
        $netProfitFound = false;

        $walk = function (array $node) use (&$walk, &$sections, &$netProfit, &$netProfitFound) {
            $name = $node['name'] ?? $node['section_name'] ?? $node['account_name'] ?? '';

            if (strcasecmp($name, 'Net Profit/Loss') === 0 || strcasecmp($name, 'Net Profit') === 0) {
                $netProfit = (float) ($node['total'] ?? 0);
                $netProfitFound = true;
            }

            $isCategory = array_key_exists('total_label', $node);
            $children = $node['account_transactions'] ?? [];

            if ($isCategory) {
                $sections[] = [
                    'name' => $name,
                    'accounts' => $this->collectLeafAccounts($children),
                    'total' => (float) ($node['total'] ?? 0),
                ];

                return;
            }

            foreach ($children as $child) {
                $walk($child);
            }
        };

        foreach ($top as $node) {
            $walk($node);
        }

        return [
            'sections' => $sections,
            'net_profit' => $netProfit,
            'net_profit_found' => $netProfitFound,
        ];
    }

    /**
     * Walk a sub-tree and return every leaf account (node with an account_id) it contains.
     */
    protected function collectLeafAccounts(array $nodes): array
    {
        $accounts = [];

        $walk = function (array $node) use (&$walk, &$accounts) {
            $accountId = $node['account_id'] ?? $node['id'] ?? null;
            $children = $node['account_transactions'] ?? [];

            if ($accountId !== null && empty($children)) {
                $accounts[] = [
                    'account_id' => (string) $accountId,
                    'name' => $node['name'] ?? $node['account_name'] ?? '',
                    'total' => (float) ($node['total'] ?? 0),
                ];

                return;
            }

            foreach ($children as $child) {
                $walk($child);
            }
        };

        foreach ($nodes as $node) {
            $walk($node);
        }

        return $accounts;
    }
}
