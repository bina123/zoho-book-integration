<?php

namespace App\Contracts;

use App\Enums\AttachmentType;

interface ZohoBooksClient
{
    public function getOrganizations(): array;

    public function getProfitLossReport(string $fromDate, string $toDate): array;

    public function getAccountTransactions(string $accountId, string $fromDate, string $toDate): array;

    public function getChartOfAccounts(): array;

    public function listInvoices(string $fromDate, string $toDate, int $perPage = 200): array;

    public function listBills(string $fromDate, string $toDate, int $perPage = 200): array;

    /**
     * @return array{contents: string, content_type: string, filename: string|null}|null
     */
    public function downloadAttachment(AttachmentType $type, string $id): ?array;
}
