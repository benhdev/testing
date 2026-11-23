<?php

namespace Database\Factories;

use App\Models\Fund;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Fund>
 */
class FundFactory extends Factory
{
    public function definition(): array
    {
        $sedol = Str::upper(Str::random(7));

        return [
            'sedol' => $sedol,
            'category' => $this->faker->randomElement(['growth', 'responsible']),
            'risk_label' => $this->faker->randomElement(['cautious', 'balanced', 'adventurous', 'responsible']),
            'market_code' => 'FUND:'.$sedol,
            'name' => 'VT AJ Bell '.$this->faker->word().' Fund',
            'sector_name' => $this->faker->randomElement([
                'Mixed Investment 0-35% Shares',
                'Mixed Investment 20-60% Shares',
                'Mixed Investment 40-85% Shares',
                'Global',
            ]),
            'currency' => 'GBX',
            'last_price' => $this->faker->randomFloat(4, 50, 500),
            'last_price_date' => $this->faker->dateTimeBetween('-5 days'),
            'ongoing_charge' => $this->faker->randomFloat(4, 0.1, 2.0),
            'analyst_rating' => $this->faker->numberBetween(1, 5),
            'analyst_rating_label' => $this->faker->randomElement(['Negative', 'Neutral', 'Positive', 'Silver', 'Gold']),
            'srri' => $this->faker->numberBetween(1, 7),
            'objective' => $this->faker->sentences(3, true),
            'is_active' => true,
            'fetched_at' => now(),
        ];
    }

    /**
     * A fund that's been seeded but has no upstream data yet — i.e. the state
     * immediately after `migrate:fresh --seed` but before `funds:refresh`.
     */
    public function unfetched(): static
    {
        return $this->state(fn () => [
            'market_code' => null,
            'name' => null,
            'sector_name' => null,
            'currency' => null,
            'last_price' => null,
            'last_price_date' => null,
            'ongoing_charge' => null,
            'analyst_rating' => null,
            'analyst_rating_label' => null,
            'srri' => null,
            'objective' => null,
            'fetched_at' => null,
        ]);
    }

    public function inactive(): static
    {
        return $this->state(fn () => ['is_active' => false]);
    }
}
