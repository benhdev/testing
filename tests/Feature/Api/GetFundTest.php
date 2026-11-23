<?php

namespace Tests\Feature\Api;

use App\Models\Fund;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class GetFundTest extends TestCase
{
    use RefreshDatabase;

    public function test_returns_fund_by_sedol(): void
    {
        $fund = Fund::factory()->create(['sedol' => 'BYW8RV9', 'name' => 'Cautious Fund']);
        $fund->documents()->create(['type' => 'Factsheet', 'url' => 'https://doc.example']);

        $response = $this->getJson('/api/funds/BYW8RV9');

        $response->assertOk()
            ->assertJsonPath('data.sedol', 'BYW8RV9')
            ->assertJsonPath('data.name', 'Cautious Fund')
            ->assertJsonCount(1, 'data.documents');
    }

    public function test_returns_404_for_unknown_sedol(): void
    {
        $response = $this->getJson('/api/funds/UNKNOWN');

        $response->assertStatus(404);
    }
}
