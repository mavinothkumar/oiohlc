<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('entries', function (Blueprint $table) {
            $table->id();

            $table->string('underlying_symbol');   // NIFTY
            $table->string('exchange');            // NSE
            $table->date('expiry');                // option expiry date
            $table->string('instrument_type');     // CE / PE
            $table->integer('strike');             // 26300

            $table->enum('side', ['BUY', 'SELL']); // buy or sell
            $table->integer('quantity');           // lots or units

            $table->date('entry_date');            // trade date
            $table->time('entry_time');            // 09:15, 09:20 etc (5‑min grid)

            $table->decimal('entry_price', 10, 2); // premium, e.g. 488.40

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('entries');
    }
};

