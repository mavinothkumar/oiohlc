<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sim_orders', function (Blueprint $table) {
            $table->id();
            $table->foreignId('position_id')
                  ->constrained('sim_positions')
                  ->cascadeOnDelete();
            $table->string('session_id')->index();
            $table->date('trade_date');
            $table->enum('order_type', ['entry', 'partial_exit', 'full_exit']);
            $table->string('side', 4);                    // BUY or SELL
            $table->decimal('price', 10, 2);              // execution price
            $table->integer('qty');                       // lots * lot_size
            $table->integer('lots')->default(1);          // number of lots
            $table->decimal('pnl', 10, 2)->default(0);   // 0 for entries
            $table->timestamp('executed_at');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sim_orders');
    }
};
