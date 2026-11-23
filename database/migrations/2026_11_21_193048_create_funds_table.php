<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('funds', function (Blueprint $table) {
            $table->id();

            // Natural key from upstream (`marketCode` strips the "FUND:" prefix).
            $table->string('sedol', 10)->unique();

            // Category / risk are not in the upstream JSON — they come from the
            // seeder's SEDOL mapping. Refresh never overwrites these columns.
            $table->string('category', 32)->index();
            $table->string('risk_label', 32)->nullable();

            // Upstream-sourced scalars. All nullable because a freshly seeded
            // fund has no upstream data yet — fetched_at being NULL signals this.
            $table->string('market_code', 32)->nullable();
            $table->string('name')->nullable();
            $table->string('sector_name')->nullable();
            $table->string('currency', 10)->nullable();
            $table->decimal('last_price', 14, 4)->nullable();
            $table->date('last_price_date')->nullable();
            $table->decimal('ongoing_charge', 6, 4)->nullable()->index();
            $table->unsignedTinyInteger('analyst_rating')->nullable()->index();
            $table->string('analyst_rating_label', 32)->nullable();
            $table->unsignedTinyInteger('srri')->nullable();
            $table->string('objective', 5000)->nullable();

            $table->boolean('is_active')->default(true)->index();
            $table->timestamp('fetched_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('funds');
    }
};
