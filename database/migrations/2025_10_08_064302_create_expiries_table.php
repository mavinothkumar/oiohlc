<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('expiries', function (Blueprint $table) {
            $table->id();
            $table->date('expiry_date')->index()->nullable(); // For easy querying
            $table->string('instrument_type')->nullable(); // FUT, OPT, etc.
            $table->string('trading_symbol')->nullable(); // Optional
            $table->boolean('is_current')->default(false)->index();
            $table->boolean('is_next')->default(false)->index();
            $table->timestamps();

            $table->bigInteger('expiry')->index();
            $table->string('exchange')->index();
            $table->string('segment')->index();
            $table->index(['trading_symbol', 'instrument_type', 'expiry_date']);

        });
    }

    public function down(): void
    {
        Schema::dropIfExists('expiries');
    }
};
