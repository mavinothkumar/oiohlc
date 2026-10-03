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
        // 1. underlyings
        if (!Schema::hasTable('underlyings')) {
            Schema::create('underlyings', function (Blueprint $table) {
                $table->id();
                $table->string('symbol', 32)->unique();
                $table->string('name', 128);
                $table->string('exchange', 32)->default('NSE');
                $table->string('segment', 32)->default('INDEX');
                $table->decimal('tick_size', 8, 2)->default(0.05);
                $table->unsignedInteger('lot_size')->default(50);
                $table->string('status', 32)->default('ACTIVE');
                $table->timestamps();
            });
        }

        // 2. Enhance existing or create instruments table
        if (!Schema::hasTable('instruments')) {
            Schema::create('instruments', function (Blueprint $table) {
                $table->id();
                $table->foreignId('underlying_id')->nullable()->constrained('underlyings')->nullOnDelete();
                $table->string('symbol', 64)->index();
                $table->string('instrument_type', 32)->default('OPTION'); // SPOT, FUTURE, OPTION
                $table->string('option_type', 8)->nullable(); // CE, PE
                $table->decimal('strike', 12, 2)->nullable()->index();
                $table->date('expiry')->nullable()->index();
                $table->unsignedInteger('lot_size')->default(50);
                $table->decimal('tick_size', 8, 2)->default(0.05);
                $table->string('status', 32)->default('ACTIVE');
                $table->json('metadata')->nullable();
                $table->timestamps();
            });
        } else {
            Schema::table('instruments', function (Blueprint $table) {
                if (!Schema::hasColumn('instruments', 'underlying_id')) {
                    $table->unsignedBigInteger('underlying_id')->nullable()->after('id')->index();
                }
                if (!Schema::hasColumn('instruments', 'symbol')) {
                    $table->string('symbol', 64)->nullable()->after('underlying_id')->index();
                }
                if (!Schema::hasColumn('instruments', 'strike')) {
                    $table->decimal('strike', 12, 2)->nullable()->after('option_type')->index();
                }
                if (!Schema::hasColumn('instruments', 'status')) {
                    $table->string('status', 32)->default('ACTIVE')->after('tick_size');
                }
                if (!Schema::hasColumn('instruments', 'metadata')) {
                    $table->json('metadata')->nullable()->after('status');
                }
            });
        }

        // 3. strategies
        if (!Schema::hasTable('strategies')) {
            Schema::create('strategies', function (Blueprint $table) {
                $table->id();
                $table->string('key', 64)->unique();
                $table->string('name', 128);
                $table->text('description')->nullable();
                $table->string('category', 64)->default('NEUTRAL'); // NEUTRAL, BULLISH, BEARISH, VOLATILITY, INCOME
                $table->string('version', 16)->default('1.0');
                $table->json('configuration')->nullable();
                $table->string('status', 32)->default('ACTIVE');
                $table->timestamps();
            });
        }

        // 4. positions
        if (!Schema::hasTable('positions')) {
            Schema::create('positions', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('user_id')->nullable()->index();
                $table->foreignId('strategy_id')->nullable()->constrained('strategies')->nullOnDelete();
                $table->foreignId('underlying_id')->nullable()->constrained('underlyings')->nullOnDelete();
                $table->string('name', 128);
                $table->string('status', 32)->default('OPEN'); // DRAFT, OPEN, PAUSED, CLOSED, ARCHIVED
                $table->dateTime('entry_timestamp')->nullable();
                $table->dateTime('exit_timestamp')->nullable();
                $table->decimal('entry_underlying_price', 12, 4)->nullable();
                $table->decimal('current_underlying_price', 12, 4)->nullable();
                $table->decimal('entry_iv', 8, 4)->nullable();
                $table->decimal('current_iv', 8, 4)->nullable();
                $table->decimal('initial_credit', 12, 4)->default(0);
                $table->decimal('current_value', 12, 4)->default(0);
                $table->decimal('realized_pnl', 12, 4)->default(0);
                $table->decimal('unrealized_pnl', 12, 4)->default(0);
                $table->decimal('total_pnl', 12, 4)->default(0);
                $table->json('metadata')->nullable();
                $table->timestamps();
            });
        }

        // 5. position_legs
        if (!Schema::hasTable('position_legs')) {
            Schema::create('position_legs', function (Blueprint $table) {
                $table->id();
                $table->foreignId('position_id')->constrained('positions')->cascadeOnDelete();
                $table->unsignedBigInteger('instrument_id')->nullable()->index();
                $table->string('side', 8); // BUY, SELL
                $table->integer('quantity');
                $table->decimal('entry_price', 12, 4);
                $table->decimal('current_price', 12, 4)->nullable();
                $table->dateTime('entry_timestamp')->nullable();
                $table->decimal('exit_price', 12, 4)->nullable();
                $table->dateTime('exit_timestamp')->nullable();
                $table->string('status', 32)->default('OPEN');
                $table->json('metadata')->nullable();
                $table->timestamps();
            });
        }

        // 6. market_snapshots
        if (!Schema::hasTable('market_snapshots')) {
            Schema::create('market_snapshots', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('instrument_id')->nullable()->index();
                $table->dateTime('timestamp')->index();
                $table->decimal('bid', 12, 4)->nullable();
                $table->decimal('ask', 12, 4)->nullable();
                $table->decimal('ltp', 12, 4)->nullable();
                $table->bigInteger('volume')->default(0);
                $table->bigInteger('open_interest')->default(0);
                $table->decimal('iv', 8, 4)->nullable();
                $table->decimal('delta', 8, 4)->nullable();
                $table->decimal('gamma', 10, 6)->nullable();
                $table->decimal('theta', 8, 4)->nullable();
                $table->decimal('vega', 8, 4)->nullable();
                $table->decimal('rho', 8, 4)->nullable();
                $table->decimal('underlying_price', 12, 4)->nullable();
                $table->string('source', 32)->default('SYSTEM');
                $table->json('metadata')->nullable();
                $table->timestamp('created_at')->useCurrent();
            });
        }

        // 7. position_snapshots
        if (!Schema::hasTable('position_snapshots')) {
            Schema::create('position_snapshots', function (Blueprint $table) {
                $table->id();
                $table->foreignId('position_id')->constrained('positions')->cascadeOnDelete();
                $table->dateTime('timestamp')->index();
                $table->decimal('underlying_price', 12, 4)->nullable();
                $table->decimal('total_value', 12, 4)->default(0);
                $table->decimal('unrealized_pnl', 12, 4)->default(0);
                $table->decimal('realized_pnl', 12, 4)->default(0);
                $table->decimal('total_pnl', 12, 4)->default(0);
                $table->decimal('delta', 10, 4)->default(0);
                $table->decimal('gamma', 12, 6)->default(0);
                $table->decimal('theta', 10, 4)->default(0);
                $table->decimal('vega', 10, 4)->default(0);
                $table->decimal('rho', 10, 4)->default(0);
                $table->decimal('iv', 8, 4)->default(0);
                $table->decimal('distance_from_strike', 10, 4)->nullable();
                $table->decimal('distance_to_upper_breakeven', 10, 4)->nullable();
                $table->decimal('distance_to_lower_breakeven', 10, 4)->nullable();
                $table->decimal('expected_move', 10, 4)->nullable();
                $table->string('regime', 64)->default('UNKNOWN');
                $table->string('risk_state', 32)->default('NORMAL');
                $table->json('metadata')->nullable();
                $table->timestamp('created_at')->useCurrent();
            });
        }

        // 8. scenarios
        if (!Schema::hasTable('scenarios')) {
            Schema::create('scenarios', function (Blueprint $table) {
                $table->id();
                $table->foreignId('position_id')->constrained('positions')->cascadeOnDelete();
                $table->string('name', 128);
                $table->decimal('underlying_change', 10, 4)->default(0);
                $table->decimal('underlying_price', 12, 4);
                $table->decimal('iv_change', 8, 4)->default(0);
                $table->integer('days_change')->default(0);
                $table->decimal('calculated_pnl', 12, 4)->default(0);
                $table->decimal('calculated_delta', 10, 4)->default(0);
                $table->decimal('calculated_gamma', 12, 6)->default(0);
                $table->decimal('calculated_theta', 10, 4)->default(0);
                $table->decimal('calculated_vega', 10, 4)->default(0);
                $table->json('metadata')->nullable();
                $table->timestamp('created_at')->useCurrent();
            });
        }

        // 9. risk_events
        if (!Schema::hasTable('risk_events')) {
            Schema::create('risk_events', function (Blueprint $table) {
                $table->id();
                $table->foreignId('position_id')->constrained('positions')->cascadeOnDelete();
                $table->dateTime('timestamp')->index();
                $table->string('event_type', 64);
                $table->string('severity', 32); // INFO, WARNING, CRITICAL, EMERGENCY
                $table->string('metric', 64);
                $table->decimal('previous_value', 12, 4)->nullable();
                $table->decimal('current_value', 12, 4)->nullable();
                $table->decimal('threshold', 12, 4)->nullable();
                $table->text('message');
                $table->json('metadata')->nullable();
                $table->timestamp('created_at')->useCurrent();
            });
        }

        // 10. analytics_audit_logs
        if (!Schema::hasTable('analytics_audit_logs')) {
            Schema::create('analytics_audit_logs', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('position_id')->nullable()->index();
                $table->unsignedBigInteger('user_id')->nullable()->index();
                $table->string('action', 64);
                $table->json('old_values')->nullable();
                $table->json('new_values')->nullable();
                $table->string('reason', 255)->nullable();
                $table->string('ip_address', 45)->nullable();
                $table->timestamp('created_at')->useCurrent();
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('analytics_audit_logs');
        Schema::dropIfExists('risk_events');
        Schema::dropIfExists('scenarios');
        Schema::dropIfExists('position_snapshots');
        Schema::dropIfExists('market_snapshots');
        Schema::dropIfExists('position_legs');
        Schema::dropIfExists('positions');
        Schema::dropIfExists('strategies');
        // Do not drop existing instruments if table had other data
        if (Schema::hasTable('instruments')) {
            Schema::table('instruments', function (Blueprint $table) {
                if (Schema::hasColumn('instruments', 'underlying_id')) {
                    $table->dropColumn(['underlying_id', 'status', 'metadata']);
                }
            });
        }
        Schema::dropIfExists('underlyings');
    }
};
