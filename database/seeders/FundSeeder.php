<?php

namespace Database\Seeders;

use App\Models\Fund;
use Illuminate\Database\Seeder;

class FundSeeder extends Seeder
{
    /**
     * Seed the four SEDOLs the brief tells us about with their category and
     * risk mapping. Everything else (name, price, ratings, …) is populated by
     * `funds:refresh` from the upstream JSON — this seeder only establishes
     * the mapping that upstream doesn't carry.
     *
     * Running this seeder repeatedly is safe — we `firstOrCreate` on SEDOL.
     */
    public function run(): void
    {
        $catalogue = [
            ['sedol' => 'BYW8RV9', 'category' => 'growth',      'risk_label' => 'cautious'],
            ['sedol' => 'BYW8RX1', 'category' => 'growth',      'risk_label' => 'balanced'],
            ['sedol' => 'BYW8VG2', 'category' => 'growth',      'risk_label' => 'adventurous'],
            ['sedol' => 'BN0S2V9', 'category' => 'responsible', 'risk_label' => 'responsible'],
        ];

        foreach ($catalogue as $row) {
            Fund::firstOrCreate(
                ['sedol' => $row['sedol']],
                [
                    'category' => $row['category'],
                    'risk_label' => $row['risk_label'],
                    'is_active' => true,
                ],
            );
        }
    }
}
