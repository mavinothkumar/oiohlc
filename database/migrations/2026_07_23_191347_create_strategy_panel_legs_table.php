<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('strategy_panel_legs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('strategy_panel_id')->constrained()->cascadeOnDelete();
            $table->decimal('strike_price', 10, 2);
            $table->string('option_type', 10);
            $table->string('expiry_type', 20); // Current, Next
            $table->integer('quantity'); // Lots multiplier * 65
            $table->string('side', 10); // Buy, Sell
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('strategy_panel_legs');
    }
};
