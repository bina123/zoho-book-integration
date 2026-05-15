<?php

namespace App\Services;

use App\Contracts\OrganizationStore;
use App\Contracts\ZohoBooksClient;
use App\Enums\TransactionType;
use App\Support\ReportPresenter;
use Carbon\Carbon;
use Illuminate\Support\Facades\Cache;

/**
 * Calls Zoho's account-transactions endpoint and normalises the response into
 * a flat, predictable structure for the API/view layer.
 *
 * Lives in its own class so the controller stays thin (the previous in-line
 * version was a 120-line method that did filtering, numeric coercion,
 * account-name lookup, totals computation, and response shaping all at once).
 */
final class TransactionsAssembler
{
    public const CACHE_TTL = 60;

    public function __construct(
        protected ZohoBooksClient $booksClient,
        protected OrganizationStore $organizationStore,
    ) {}

    public function assemble(string $accountId, string $month): array
    {
        $bounds = $this->monthBounds($month);
        $response = $this->fetch($accountId, $bounds['from'], $bounds['to']);

        $wrapper = $response['account_transactions'][0]
            ?? $response['data']['account_transactions'][0]
            ?? [];

        $rows = $wrapper['account_transactions'] ?? [];
        $pageContext = $response['page_context'] ?? [];

        $transactions = $this->buildTransactions($rows, $accountId);
        $accountName = $this->resolveAccountName($rows, $accountId);
        $totals = $this->totalsFromFiltered($transactions);

        return [
            'meta' => $this->buildMeta($pageContext, $accountName),
            'transactions' => $transactions,
            'opening_balance' => $this->balance($wrapper['opening_balance'] ?? null, 'Opening Balance'),
            'closing_balance' => $this->balance($wrapper['closing_balance'] ?? null, 'Closing Balance'),
            'totals' => $totals,
        ];
    }

    protected function fetch(string $accountId, string $from, string $to): array
    {
        $cacheKey = "zoho:txn:{$accountId}:{$from}:{$to}";

        return Cache::remember(
            $cacheKey,
            self::CACHE_TTL,
            fn () => $this->booksClient->getAccountTransactions($accountId, $from, $to),
        );
    }

    protected function buildTransactions(array $rows, string $accountId): array
    {
        $out = [];
        foreach ($rows as $row) {
            // Zoho returns every leg of every JE — keep only the one we asked for.
            if ((string) ($row['account_id'] ?? '') !== $accountId) {
                continue;
            }

            $type = TransactionType::fromZoho($row['transaction_type'] ?? null);
            $debit = $this->numeric($row['debit'] ?? null);
            $credit = $this->numeric($row['credit'] ?? null);

            $out[] = [
                'date' => $row['date'] ?? $row['transaction_date'] ?? null,
                'account' => $row['account_name'] ?? null,
                'details' => $row['transaction_details']
                    ?? $row['payee']
                    ?? $row['contact_name']
                    ?? '',
                'transaction_type' => $row['transaction_type'] ?? null,
                'transaction_number' => $row['entity_number']
                    ?? $row['transaction_number']
                    ?? '',
                'reference_number' => $row['reference_number'] ?? '',
                'debit' => $debit,
                'credit' => $credit,
                'amount' => $debit !== 0.0 ? $debit : $credit,
                'amount_label' => $row['net_amount'] ?? '',
                'entity_id' => $row['transaction_id'] ?? $row['entity_id'] ?? null,
                'attachment_type' => $type->attachmentType()?->value,
            ];
        }

        return $out;
    }

    protected function resolveAccountName(array $rows, string $accountId): ?string
    {
        foreach ($rows as $row) {
            if ((string) ($row['account_id'] ?? '') === $accountId) {
                return $row['account_name'] ?? null;
            }
        }

        return null;
    }

    protected function totalsFromFiltered(array $transactions): array
    {
        return [
            'debit' => array_sum(array_column($transactions, 'debit')),
            'credit' => array_sum(array_column($transactions, 'credit')),
        ];
    }

    protected function balance(?array $raw, string $defaultName): ?array
    {
        if ($raw === null) {
            return null;
        }

        return [
            'label' => $raw['date'] ?? $defaultName,
            'name' => $raw['name'] ?? $defaultName,
            'debit' => $this->numeric($raw['debit'] ?? $raw['account_debit_balance'] ?? null),
            'credit' => $this->numeric($raw['credit'] ?? $raw['account_credit_balance'] ?? null),
        ];
    }

    protected function buildMeta(array $pageContext, ?string $accountName): array
    {
        $from = $pageContext['from_date'] ?? null;
        $to = $pageContext['to_date'] ?? null;

        return [
            'organization_name' => $this->organizationStore->getOrganizationName(),
            'account_name' => $accountName,
            'basis' => $pageContext['report_basis'] ?? 'Accrual',
            'from_date' => $from,
            'to_date' => $to,
            'from_date_display' => $from ? Carbon::parse($from)->format(ReportPresenter::DATE_DISPLAY_FORMAT) : null,
            'to_date_display' => $to ? Carbon::parse($to)->format(ReportPresenter::DATE_DISPLAY_FORMAT) : null,
        ];
    }

    protected function numeric(mixed $v): float
    {
        if ($v === '' || $v === null) {
            return 0.0;
        }

        return (float) (is_string($v) ? str_replace(',', '', $v) : $v);
    }

    protected function monthBounds(string $month): array
    {
        $start = Carbon::createFromFormat('Y-m', $month)->startOfMonth();

        return [
            'from' => $start->toDateString(),
            'to' => $start->copy()->endOfMonth()->toDateString(),
        ];
    }
}
