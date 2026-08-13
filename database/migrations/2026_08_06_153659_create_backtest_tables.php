<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('backtest_strategies', function (Blueprint $table) {
            $table->id();

            $table->string('slug', 100)->unique();
            $table->string('name', 150);
            $table->unsignedInteger('version')->default(1);

            /*
             * Example:
             * {
             *   "legs": [
             *     {
             *       "side": "SELL",
             *       "option_type": "CE",
             *       "moneyness": "ATM",
             *       "strike_offset": 0,
             *       "lots": 1
             *     }
             *   ]
             * }
             */
            $table->json('definition');

            /*
             * Entry/exit rules, stop loss, target, snapshot frequency, etc.
             */
            $table->json('parameters')->nullable();

            $table->boolean('is_active')->default(true);

            $table->timestamps();

            $table->unique(
                ['slug', 'version'],
                'backtest_strategies_slug_version_unique'
            );
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('backtest_strategies');
    }
};
