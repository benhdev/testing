<?php

namespace Tests\Feature\Api;

use App\Models\Fund;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ListFundsTest extends TestCase
{
    use RefreshDatabase;

    public function test_returns_paginated_active_funds(): void
    {
        Fund::factory()->count(3)->create();

        $response = $this->getJson('/api/funds');

        $response->assertOk()
            ->assertJsonCount(3, 'data')
            ->assertJsonStructure([
                'data' => [
                    ['sedol', 'name', 'category', 'ongoing_charge', 'analyst_rating'],
                ],
                'links',
                'meta',
            ]);
    }

    public function test_excludes_inactive_funds_by_default(): void
    {
        Fund::factory()->create(['name' => 'Active One']);
        Fund::factory()->inactive()->create(['name' => 'Inactive One']);

        $response = $this->getJson('/api/funds');

        $response->assertOk()->assertJsonCount(1, 'data');
        $this->assertSame('Active One', $response->json('data.0.name'));
    }

    public function test_filters_by_category(): void
    {
        Fund::factory()->create(['category' => 'growth', 'name' => 'Growth Fund']);
        Fund::factory()->create(['category' => 'responsible', 'name' => 'Responsible Fund']);

        $response = $this->getJson('/api/funds?category=responsible');

        $response->assertOk()->assertJsonCount(1, 'data');
        $this->assertSame('Responsible Fund', $response->json('data.0.name'));
    }

    public function test_filters_by_min_analyst_rating(): void
    {
        Fund::factory()->create(['analyst_rating' => 2, 'name' => 'Low Rated']);
        Fund::factory()->create(['analyst_rating' => 4, 'name' => 'High Rated']);

        $response = $this->getJson('/api/funds?min_analyst_rating=3');

        $response->assertOk()->assertJsonCount(1, 'data');
        $this->assertSame('High Rated', $response->json('data.0.name'));
    }

    public function test_filters_by_max_ongoing_charge(): void
    {
        Fund::factory()->create(['ongoing_charge' => '1.5000', 'name' => 'Expensive']);
        Fund::factory()->create(['ongoing_charge' => '0.3000', 'name' => 'Cheap']);

        $response = $this->getJson('/api/funds?max_ongoing_charge=0.5');

        $response->assertOk()->assertJsonCount(1, 'data');
        $this->assertSame('Cheap', $response->json('data.0.name'));
    }

    public function test_sorts_descending_with_prefix(): void
    {
        Fund::factory()->create(['analyst_rating' => 2, 'name' => 'Lower']);
        Fund::factory()->create(['analyst_rating' => 5, 'name' => 'Highest']);
        Fund::factory()->create(['analyst_rating' => 3, 'name' => 'Middle']);

        $response = $this->getJson('/api/funds?sort=-analyst_rating');

        $response->assertOk();
        $names = array_column($response->json('data'), 'name');
        $this->assertSame(['Highest', 'Middle', 'Lower'], $names);
    }

    public function test_respects_per_page(): void
    {
        Fund::factory()->count(5)->create();

        $response = $this->getJson('/api/funds?per_page=2');

        $response->assertOk()
            ->assertJsonCount(2, 'data')
            ->assertJsonPath('meta.per_page', 2)
            ->assertJsonPath('meta.total', 5);
    }

    public function test_rejects_invalid_filter_value(): void
    {
        $response = $this->getJson('/api/funds?category=invalid');

        $response->assertStatus(422)->assertJsonValidationErrors(['category']);
    }

    public function test_rejects_invalid_sort(): void
    {
        $response = $this->getJson('/api/funds?sort=fetched_at');

        $response->assertStatus(422)->assertJsonValidationErrors(['sort']);
    }

    public function test_rejects_per_page_over_cap(): void
    {
        $response = $this->getJson('/api/funds?per_page=500');

        $response->assertStatus(422)->assertJsonValidationErrors(['per_page']);
    }
}
