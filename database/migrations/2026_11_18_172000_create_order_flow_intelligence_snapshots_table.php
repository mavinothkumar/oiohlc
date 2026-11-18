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
        Schema::create('order_flow_intelligence_snapshots', function (Blueprint $table) {
            $table->id();
            $table->date('trade_date')->index();
            $table->string('snapshot_time', 12)->index()->comment('e.g. 10:46:06');
            $table->string('symbol', 50)->default('NIFTY')->index();
            $table->string('instrument', 100)->nullable();
            $table->decimal('ltp', 10, 2)->nullable();
            $table->decimal('index_ltp', 10, 2)->nullable();
            $table->integer('conviction_score')->default(50);
            $table->string('regime', 50)->nullable()->comment('BULLISH_SQUEEZE, BEARISH_EXPANSION, EQUILIBRIUM_CHOP');
            $table->string('bias', 20)->default('BALANCED')->comment('BUY, SELL, BALANCED');
            $table->decimal('dominance_ratio', 6, 2)->default(1.00);
            $table->bigInteger('net_volume')->default(0);
            $table->unsignedBigInteger('total_ask_volume')->default(0);
            $table->unsignedBigInteger('total_bid_volume')->default(0);
            $table->decimal('td_sup', 10, 2)->nullable()->comment('Today #1 Institutional Demand anchor');
            $table->unsignedBigInteger('td_sup_qty')->nullable();
            $table->decimal('td_res', 10, 2)->nullable()->comment('Today #1 Institutional Supply anchor');
            $table->unsignedBigInteger('td_res_qty')->nullable();
            $table->decimal('yd_sup', 10, 2)->nullable()->comment('Yesterday #1 Demand anchor');
            $table->decimal('yd_res', 10, 2)->nullable()->comment('Yesterday #1 Supply anchor');
            $table->decimal('target1', 10, 2)->nullable();
            $table->decimal('target2', 10, 2)->nullable();
            $table->decimal('concrete_sl', 10, 2)->nullable();
            $table->string('trade_status', 30)->nullable()->comment('FRESH, IN_FLIGHT, TARGET_HIT, INVALIDATED');
            $table->string('whale_status', 255)->nullable();
            $table->string('fomo_status', 255)->nullable();
            $table->text('past_story')->nullable();
            $table->text('present_story')->nullable();
            $table->text('future_story')->nullable();
            $table->text('playbook_suggestion')->nullable();
            $table->json('meta')->nullable();
            $table->timestamps();

            // Indexing for fast historical querying and EOD reporting
            $table->index(['trade_date', 'symbol', 'snapshot_time'], 'idx_snap_time');
            $table->index(['trade_date', 'symbol', 'bias'], 'idx_snap_bias');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('order_flow_intelligence_snapshots');
    }
};
