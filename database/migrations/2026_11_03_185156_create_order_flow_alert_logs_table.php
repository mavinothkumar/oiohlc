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
        Schema::create('order_flow_alert_logs', function (Blueprint $table) {
            $table->id();
            $table->string('mode', 20)->default('live')->comment('live or sim');
            $table->date('trade_date')->index();
            $table->string('alert_time', 12)->index()->comment('e.g. 09:42:15');
            $table->string('symbol', 50)->default('NIFTY');
            $table->string('instrument', 100)->nullable();
            $table->string('alert_type', 50)->index()->comment('STACK_BUY, STACK_SELL, BLOCK_BUY, BLOCK_SELL, STRATEGY_BUY, STRATEGY_SELL, VACUUM_BUY, VACUUM_SELL, ABSORPTION_BUY, ABSORPTION_SELL');
            $table->string('side', 15)->index()->comment('BUY, SELL, NEUTRAL');
            $table->decimal('price', 10, 2)->default(0);
            $table->unsignedBigInteger('quantity')->nullable();
            $table->integer('level')->nullable();
            $table->integer('confluence_score')->nullable();
            $table->string('title', 255);
            $table->text('details')->nullable();
            $table->string('guidance', 255)->nullable();
            $table->string('sound', 50)->nullable();
            $table->json('meta')->nullable();
            $table->timestamps();

            $table->index(['trade_date', 'alert_type']);
            $table->index(['trade_date', 'side']);
            $table->index(['trade_date', 'symbol']);
            $table->index(['trade_date', 'mode']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('order_flow_alert_logs');
    }
};
