<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sim_positions', function (Blueprint $table) {
            $table->id();
            $table->string('session_id')->index();
            $table->date('trade_date');
            $table->date('expiry');
            $table->string('underlying', 20)->default('NIFTY');
            $table->integer('strike');
            $table->string('instrument_type', 2);          // CE or PE
            $table->string('side', 4);                     // BUY or SELL
            $table->decimal('avg_entry', 10, 2)->default(0);
            $table->integer('total_qty')->default(0);      // cumulative qty entered
            $table->integer('open_qty')->default(0);       // remaining open qty
            $table->decimal('realized_pnl', 10, 2)->default(0);
            $table->enum('status', ['open', 'closed'])->default('open');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sim_positions');
    }
};
