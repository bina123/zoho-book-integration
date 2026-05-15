<?php

namespace Tests\Unit;

use App\Contracts\OrganizationStore;
use App\Contracts\ZohoBooksClient;
use App\Enums\AttachmentType;
use App\Services\TransactionsAssembler;
use Illuminate\Foundation\Testing\TestCase;
use Tests\CreatesApplication;

final class TransactionsAssemblerTest extends TestCase
{
    use CreatesApplication;

    public function test_filters_to_requested_account_only(): void
    {
        $assembler = $this->buildAssembler();

        $result = $assembler->assemble('3818482000000000567', '2026-05');

        $this->assertCount(5, $result['transactions']);
        foreach ($result['transactions'] as $t) {
            $this->assertSame('Cost of Goods Sold', $t['account']);
        }
    }

    public function test_computes_totals_from_filtered_rows_only(): void
    {
        $assembler = $this->buildAssembler();

        $result = $assembler->assemble('3818482000000000567', '2026-05');

        $this->assertSame(87500.0, $result['totals']['debit']);
        $this->assertSame(0.0, $result['totals']['credit']);
    }

    public function test_maps_bill_transaction_type_to_bill_attachment(): void
    {
        $assembler = $this->buildAssembler();

        $result = $assembler->assemble('3818482000000000567', '2026-05');

        $this->assertSame(AttachmentType::Bill->value, $result['transactions'][0]['attachment_type']);
    }

    public function test_includes_opening_and_closing_balances(): void
    {
        $assembler = $this->buildAssembler();

        $result = $assembler->assemble('3818482000000000567', '2026-05');

        $this->assertSame(75000.0, $result['opening_balance']['debit']);
        $this->assertSame(162500.0, $result['closing_balance']['debit']);
    }

    public function test_meta_carries_organization_account_and_period(): void
    {
        $assembler = $this->buildAssembler('Test Company');

        $result = $assembler->assemble('3818482000000000567', '2026-05');

        $this->assertSame('Test Company', $result['meta']['organization_name']);
        $this->assertSame('Cost of Goods Sold', $result['meta']['account_name']);
        $this->assertSame('2026-05-01', $result['meta']['from_date']);
        $this->assertSame('01/05/2026', $result['meta']['from_date_display']);
    }

    private function buildAssembler(string $orgName = 'Test Company'): TransactionsAssembler
    {
        $fixture = json_decode(file_get_contents(__DIR__.'/../fixtures/zoho-transactions.json'), true);

        $client = $this->createMock(ZohoBooksClient::class);
        $client->method('getAccountTransactions')->willReturn($fixture);

        $orgStore = $this->createMock(OrganizationStore::class);
        $orgStore->method('getOrganizationName')->willReturn($orgName);

        return new TransactionsAssembler($client, $orgStore);
    }
}
