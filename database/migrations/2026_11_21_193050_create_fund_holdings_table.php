<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('fund_holdings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('fund_id')->constrained()->cascadeOnDelete();
            $table->unsignedTinyInteger('position'); // 1..10 for top-10 ordering
            $table->string('name');
            $table->decimal('weighting', 8, 5);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('fund_holdings');
    }
};
