<?php

namespace Database\Factories;

use App\Models\Fund;
use App\Models\FundDocument;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<FundDocument>
 */
class FundDocumentFactory extends Factory
{
    public function definition(): array
    {
        return [
            'fund_id' => Fund::factory(),
            'type' => $this->faker->randomElement(['Factsheet', 'KIID', 'Prospectus']),
            'external_id' => (string) $this->faker->numberBetween(100_000_000, 999_999_999),
            'url' => $this->faker->url(),
        ];
    }
}
