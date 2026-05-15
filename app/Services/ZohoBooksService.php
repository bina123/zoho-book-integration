<?php

namespace App\Services;

use App\Contracts\OrganizationStore;
use App\Contracts\ZohoAuthClient;
use App\Contracts\ZohoBooksClient;
use App\Enums\AttachmentType;
use App\Exceptions\ZohoApiException;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;
use Illuminate\Support\Facades\Log;
use Psr\Http\Message\ResponseInterface;

final class ZohoBooksService implements ZohoBooksClient
{
    protected string $apiUrl;

    public function __construct(
        protected ZohoAuthClient $authService,
        protected OrganizationStore $organizationStore,
        protected Client $client,
    ) {
        $this->apiUrl = rtrim((string) config('zoho.urls.api'), '/');
    }

    protected function organizationId(): string
    {
        $orgId = $this->organizationStore->getOrganizationId();

        if (! $orgId) {
            throw new ZohoApiException(
                'No Zoho organization selected. Visit /auth/zoho/organizations to choose one.',
                422
            );
        }

        return $orgId;
    }

    protected function getHeaders(): array
    {
        $accessToken = $this->authService->getValidAccessToken();

        return [
            'Authorization' => "Zoho-oauthtoken {$accessToken}",
            'Accept' => 'application/json',
        ];
    }

    protected function rawRequest(string $method, string $endpoint, array $options = [], bool $useOrg = true): ResponseInterface
    {
        $url = "{$this->apiUrl}/{$endpoint}";

        $query = $options['query'] ?? [];
        if ($useOrg) {
            $query = array_merge($query, ['organization_id' => $this->organizationId()]);
        }
        $options['query'] = $query;
        $options['headers'] = array_merge($this->getHeaders(), $options['headers'] ?? []);

        Log::debug('Zoho API request', [
            'method' => $method,
            'url' => $url,
            'query' => array_diff_key($query, ['organization_id' => true]),
        ]);

        try {
            return $this->client->request($method, $url, $options);
        } catch (GuzzleException $e) {
            Log::error('Zoho API request transport error', [
                'method' => $method,
                'endpoint' => $endpoint,
                'error' => $e->getMessage(),
            ]);

            throw new ZohoApiException(
                'Zoho API request failed: '.$e->getMessage(),
                502,
                ['details' => $e->getMessage()],
                $e instanceof \Throwable ? $e : null
            );
        }
    }

    protected function request(string $method, string $endpoint, array $options = [], bool $useOrg = true): array
    {
        $response = $this->rawRequest($method, $endpoint, $options, $useOrg);
        $body = (string) $response->getBody();
        $status = $response->getStatusCode();
        $data = json_decode($body, true) ?: [];

        if ($status >= 400 || (isset($data['code']) && (int) $data['code'] !== 0)) {
            throw new ZohoApiException(
                $data['message'] ?? "Zoho API responded with status {$status}",
                $status >= 400 ? $status : 502,
                $data
            );
        }

        return $data;
    }

    public function getOrganizations(): array
    {
        return $this->request('GET', 'organizations', [], useOrg: false);
    }

    public function getProfitLossReport(string $fromDate, string $toDate): array
    {
        return $this->request('GET', 'reports/profitandloss', [
            'query' => [
                'from_date' => $fromDate,
                'to_date' => $toDate,
            ],
        ]);
    }

    public function getAccountTransactions(string $accountId, string $fromDate, string $toDate): array
    {
        return $this->request('GET', 'reports/accounttransaction', [
            'query' => [
                'account_id' => $accountId,
                'from_date' => $fromDate,
                'to_date' => $toDate,
            ],
        ]);
    }

    public function getChartOfAccounts(): array
    {
        return $this->request('GET', 'chartofaccounts');
    }

    public function listInvoices(string $fromDate, string $toDate, int $perPage = 200): array
    {
        return $this->request('GET', 'invoices', [
            'query' => [
                'date_start' => $fromDate,
                'date_end' => $toDate,
                'per_page' => $perPage,
            ],
        ]);
    }

    public function listBills(string $fromDate, string $toDate, int $perPage = 200): array
    {
        return $this->request('GET', 'bills', [
            'query' => [
                'date_start' => $fromDate,
                'date_end' => $toDate,
                'per_page' => $perPage,
            ],
        ]);
    }

    public function downloadAttachment(AttachmentType $type, string $id): ?array
    {
        // Override the default Accept: application/json — Zoho returns the raw file binary
        // (PDF/image/etc) and rejects the request with 406 if we only accept JSON.
        $response = $this->rawRequest('GET', $type->endpoint($id), [
            'headers' => ['Accept' => '*/*'],
        ]);
        $status = $response->getStatusCode();

        if ($status === 404) {
            return null;
        }

        if ($status >= 400) {
            $body = (string) $response->getBody();
            $data = json_decode($body, true) ?: [];
            throw new ZohoApiException(
                $data['message'] ?? "Failed to download attachment ({$status})",
                $status,
                $data
            );
        }

        $disposition = $response->getHeaderLine('Content-Disposition');
        $filename = null;
        if ($disposition && preg_match('/filename\*?=([^;]+)/i', $disposition, $m)) {
            $filename = trim(str_replace(['"', "'", "UTF-8''"], '', $m[1]));
        }

        return [
            'contents' => (string) $response->getBody(),
            'content_type' => $response->getHeaderLine('Content-Type') ?: 'application/octet-stream',
            'filename' => $filename,
        ];
    }
}
