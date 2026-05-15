<?php

namespace Tests\Unit;

use App\Services\Zoho\ProfitLossParser;
use PHPUnit\Framework\TestCase;

final class ProfitLossParserTest extends TestCase
{
    private ProfitLossParser $parser;

    protected function setUp(): void
    {
        parent::setUp();
        $this->parser = new ProfitLossParser;
    }

    public function test_parses_live_zoho_response_into_expected_sections(): void
    {
        $fixture = json_decode(file_get_contents(__DIR__.'/../fixtures/zoho-pnl-may.json'), true);

        $result = $this->parser->parse($fixture);

        $sectionNames = array_column($result['sections'], 'name');

        $this->assertContains('Operating Income', $sectionNames);
        $this->assertContains('Cost of Goods Sold', $sectionNames);
        $this->assertSame(112500.0, $result['net_profit']);
        $this->assertTrue($result['net_profit_found']);
    }

    public function test_extracts_leaf_accounts_with_their_account_ids(): void
    {
        $fixture = json_decode(file_get_contents(__DIR__.'/../fixtures/zoho-pnl-may.json'), true);

        $result = $this->parser->parse($fixture);

        $income = $this->findSection($result['sections'], 'Operating Income');
        $this->assertNotNull($income);
        $this->assertCount(1, $income['accounts']);
        $this->assertSame('Sales', $income['accounts'][0]['name']);
        $this->assertSame(200000.0, $income['accounts'][0]['total']);
        $this->assertNotEmpty($income['accounts'][0]['account_id']);
    }

    public function test_aggregates_section_totals_from_zoho(): void
    {
        $fixture = json_decode(file_get_contents(__DIR__.'/../fixtures/zoho-pnl-may.json'), true);

        $result = $this->parser->parse($fixture);

        $cogs = $this->findSection($result['sections'], 'Cost of Goods Sold');
        $this->assertSame(87500.0, $cogs['total']);
    }

    public function test_handles_empty_response_gracefully(): void
    {
        $result = $this->parser->parse([]);

        $this->assertSame([], $result['sections']);
        $this->assertSame(0.0, $result['net_profit']);
        $this->assertFalse($result['net_profit_found']);
    }

    public function test_handles_missing_total_label_by_treating_as_wrapper(): void
    {
        // A subtotal wrapper without total_label should be descended into, not emitted.
        $payload = [
            'profit_and_loss' => [
                [
                    'name' => 'Gross Profit',
                    'total' => 100,
                    'account_transactions' => [
                        [
                            'name' => 'Operating Income',
                            'total_label' => 'Total Operating Income',
                            'total' => 100,
                            'account_transactions' => [
                                ['account_id' => 'A1', 'name' => 'Sales', 'total' => 100],
                            ],
                        ],
                    ],
                ],
            ],
        ];

        $result = $this->parser->parse($payload);

        $this->assertCount(1, $result['sections']);
        $this->assertSame('Operating Income', $result['sections'][0]['name']);
    }

    private function findSection(array $sections, string $name): ?array
    {
        foreach ($sections as $s) {
            if ($s['name'] === $name) {
                return $s;
            }
        }

        return null;
    }
}
