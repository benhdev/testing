<?php

namespace Tests\Feature\Actions;

use App\Actions\RefreshFunds;
use App\Exceptions\FundFetchException;
use App\Models\Fund;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class RefreshFundsTest extends TestCase
{
    use RefreshDatabase;

    public function test_populates_fund_data_from_upstream(): void
    {
        Fund::factory()->unfetched()->create([
            'sedol' => 'BYW8RV9',
            'category' => 'growth',
            'risk_label' => 'cautious',
        ]);

        Http::fake([
            '*BYW8RV9.json' => Http::response($this->samplePayload('BYW8RV9', 'VT AJ Bell Cautious I Acc')),
        ]);

        $summary = (new RefreshFunds)->handle();

        $this->assertSame(['ok' => 1, 'invalid' => 0], $summary);

        $fund = Fund::query()->where('sedol', 'BYW8RV9')->firstOrFail();
        $this->assertSame('VT AJ Bell Cautious I Acc', $fund->name);
        $this->assertSame('FUND:BYW8RV9', $fund->market_code);
        $this->assertSame('118.5610', $fund->last_price);       // decimal:4 cast → string
        $this->assertSame('0.3100', $fund->ongoing_charge);
        $this->assertSame(4, $fund->analyst_rating);
        $this->assertSame('Neutral', $fund->analyst_rating_label);
        $this->assertNotNull($fund->fetched_at);
        $this->assertTrue($fund->is_active);
    }

    public function test_preserves_category_and_risk_label_on_refresh(): void
    {
        Fund::factory()->unfetched()->create([
            'sedol' => 'BYW8RV9',
            'category' => 'growth',
            'risk_label' => 'cautious',
        ]);

        Http::fake([
            '*BYW8RV9.json' => Http::response($this->samplePayload('BYW8RV9')),
        ]);

        (new RefreshFunds)->handle();

        $fund = Fund::query()->where('sedol', 'BYW8RV9')->firstOrFail();
        $this->assertSame('growth', $fund->category);
        $this->assertSame('cautious', $fund->risk_label);
    }

    public function test_rewrites_child_relationships_on_refresh(): void
    {
        $fund = Fund::factory()->unfetched()->create(['sedol' => 'BYW8RV9']);
        // Pre-existing stale children that should be replaced.
        $fund->documents()->create(['type' => 'OldDoc', 'url' => 'https://old.example.com']);
        $fund->holdings()->create(['position' => 1, 'name' => 'Old Holding', 'weighting' => '1']);
        $fund->assetAllocations()->create(['label' => 'OldLabel', 'value' => '1']);

        Http::fake([
            '*BYW8RV9.json' => Http::response($this->samplePayload('BYW8RV9')),
        ]);

        (new RefreshFunds)->handle();

        $fund->refresh()->load(['documents', 'holdings', 'assetAllocations']);

        $this->assertCount(3, $fund->documents);   // sample has Factsheet/KIID/Prospectus
        $this->assertCount(2, $fund->holdings);    // sample has 2 top holdings
        $this->assertCount(5, $fund->assetAllocations);
        $this->assertNotContains('OldDoc', $fund->documents->pluck('type')->all());
    }

    public function test_skips_invalid_fund_but_continues_with_others(): void
    {
        Fund::factory()->unfetched()->create([
            'sedol' => 'BYW8RV9',
            'category' => 'growth',
            'risk_label' => 'cautious',
        ]);
        Fund::factory()->unfetched()->create([
            'sedol' => 'BYW8RX1',
            'category' => 'growth',
            'risk_label' => 'balanced',
        ]);

        Http::fake([
            '*BYW8RV9.json' => Http::response($this->samplePayload('BYW8RV9')),
            '*BYW8RX1.json' => Http::response(['data' => ['quote' => ['marketCode' => 'FUND:BYW8RX1']]]),
            // ↑ missing `name` → DTO rejects it
        ]);

        $summary = (new RefreshFunds)->handle();

        $this->assertSame(['ok' => 1, 'invalid' => 1], $summary);

        $this->assertNotNull(Fund::query()->where('sedol', 'BYW8RV9')->value('fetched_at'));
        $this->assertNull(Fund::query()->where('sedol', 'BYW8RX1')->value('fetched_at'));
    }

    public function test_total_fetch_failure_raises_fund_fetch_exception(): void
    {
        Fund::factory()->unfetched()->create(['sedol' => 'BYW8RV9']);

        Http::fake(fn () => throw new ConnectionException('cdn unreachable'));

        $this->expectException(FundFetchException::class);

        (new RefreshFunds)->handle();
    }

    /**
     * Build a realistic AJ Bell-shaped response. Values deliberately mirror the
     * actual CDN sample (`BYW8RV9.json`) so DTO and cast behaviour exercise
     * production-like data.
     */
    private function samplePayload(string $sedol, string $name = 'Sample Fund'): array
    {
        return [
            'data' => [
                'quote' => [
                    '__typename' => 'Fund',
                    'name' => $name,
                    'marketCode' => 'FUND:'.$sedol,
                    'lastPrice' => 118.561,
                    'lastPriceDate' => '2024-06-04',
                    'ongoingCharge' => 0.31,
                    'sectorName' => 'Mixed Investment 0-35% Shares',
                    'currency' => 'GBX',
                ],
                'profile' => [
                    'objective' => 'Long-term capital growth.',
                ],
                'ratings' => [
                    'analystRating' => 4,
                    'SRRI' => 4,
                    'analystRatingLabel' => 'Neutral',
                ],
                'documents' => [
                    ['id' => '1', 'type' => 'Factsheet', 'url' => 'https://doc.example/factsheet.pdf'],
                    ['id' => '2', 'type' => 'KIID',      'url' => 'https://doc.example/kiid.pdf'],
                    ['id' => '3', 'type' => 'Prospectus', 'url' => 'https://doc.example/prospectus.pdf'],
                ],
                'portfolio' => [
                    'asset' => [
                        ['label' => 'Stock',    'value' => 25.53738],
                        ['label' => 'Bond',     'value' => 54.25369],
                        ['label' => 'Cash',     'value' => 19.69435],
                        ['label' => 'Other',    'value' => 1.00772],
                        ['label' => 'Property', 'value' => 0],
                    ],
                    'top10Holdings' => [
                        ['name' => 'Vanguard UK Bond Fund', 'weighting' => 16.0041],
                        ['name' => 'iShares ESG Bond Fund', 'weighting' => 7.98883],
                    ],
                ],
            ],
        ];
    }
}
