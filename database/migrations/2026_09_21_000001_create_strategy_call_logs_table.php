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
        Schema::create('strategy_call_logs', function (Blueprint $table) {
            $table->id();
            $table->string('mode', 20)->default('live')->comment('live or history');
            $table->date('trade_date')->index();
            $table->string('signal_time', 10)->index()->comment('Start time when signal raised e.g. 10:15:00');
            $table->string('end_time', 10)->nullable()->comment('Last active time e.g. 11:45:00');
            $table->dateTime('captured_at')->index();
            $table->dateTime('last_captured_at')->nullable();
            $table->unsignedInteger('duration_minutes')->default(0);
            $table->unsignedInteger('bars_count')->default(1);
            $table->string('underlying', 100)->default('NSE_INDEX|Nifty 50');
            $table->date('expiry')->index();

            $table->string('strategy_name', 150)->default('Daily OAI V2');
            $table->unsignedBigInteger('strategy_id')->nullable();
            $table->string('setup_title', 150);
            $table->string('status_badge', 150);
            $table->string('badge_color', 30)->default('emerald');
            $table->string('action_label', 150);

            $table->integer('recommended_anchor')->nullable();
            $table->string('anchor_skew', 150)->nullable();
            $table->decimal('entry_spot', 10, 2)->comment('Spot at signal raise');
            $table->decimal('last_spot', 10, 2)->nullable()->comment('Spot at latest active or exit bar');
            $table->decimal('spot_change', 10, 2)->nullable()->comment('Net spot migration during signal');
            $table->decimal('safe_spot_min', 10, 2)->nullable();
            $table->decimal('safe_spot_max', 10, 2)->nullable();
            $table->integer('support_strike')->nullable();
            $table->integer('resistance_strike')->nullable();

            $table->string('target_pnl', 100)->nullable();
            $table->string('stop_loss_pnl', 100)->nullable();
            $table->string('expected_duration', 150)->nullable();
            $table->string('cutoff_time', 30)->default('13:20 IST');

            $table->text('headline')->nullable();
            $table->text('rationale')->nullable()->comment('Plain-English reasoning when signal was raised');
            $table->json('flow_metrics')->nullable()->comment('Metrics snapshot when signal was raised');
            $table->json('basket_legs')->nullable()->comment('Pre-computed 16-leg structure');

            $table->string('outcome_status', 50)->default('ACTIVE')->comment('ACTIVE, CONCLUDED, TARGET_HIT, STOPPED_OUT');
            $table->decimal('outcome_pnl', 10, 2)->nullable();
            $table->text('notes')->nullable();

            $table->timestamps();

            $table->unique(
                ['trade_date', 'signal_time', 'setup_title', 'underlying'],
                'strategy_call_unique_episode'
            );
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('strategy_call_logs');
    }
};
