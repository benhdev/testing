<?php

namespace Database\Factories;

use App\Models\Fund;
use App\Models\FundHolding;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<FundHolding>
 */
class FundHoldingFactory extends Factory
{
    public function definition(): array
    {
        return [
            'fund_id' => Fund::factory(),
            'position' => $this->faker->numberBetween(1, 10),
            'name' => $this->faker->company().' Fund',
            'weighting' => $this->faker->randomFloat(5, 0.5, 20),
        ];
    }
}
