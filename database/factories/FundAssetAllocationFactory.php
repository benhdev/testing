<?php

namespace Database\Factories;

use App\Models\Fund;
use App\Models\FundAssetAllocation;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<FundAssetAllocation>
 */
class FundAssetAllocationFactory extends Factory
{
    public function definition(): array
    {
        return [
            'fund_id' => Fund::factory(),
            'label' => $this->faker->randomElement(['Stock', 'Bond', 'Cash', 'Other', 'Property']),
            'value' => $this->faker->randomFloat(5, 0, 100),
        ];
    }
}
