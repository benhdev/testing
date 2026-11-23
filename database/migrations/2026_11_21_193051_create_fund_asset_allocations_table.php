<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('fund_asset_allocations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('fund_id')->constrained()->cascadeOnDelete();
            $table->string('label', 32); // Stock / Bond / Cash / Other / Property
            $table->decimal('value', 8, 5);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('fund_asset_allocations');
    }
};
