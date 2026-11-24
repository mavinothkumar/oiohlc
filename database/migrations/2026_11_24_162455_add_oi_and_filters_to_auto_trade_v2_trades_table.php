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
        Schema::table('auto_trade_v2_trades', function (Blueprint $table) {
            $table->decimal('entry_oi_net_diff_pct', 8, 2)->nullable()->after('entry_reason');
            $table->decimal('entry_oi_net_diff_lakh', 10, 2)->nullable()->after('entry_oi_net_diff_pct');
            $table->string('entry_oi_sentiment', 25)->nullable()->after('entry_oi_net_diff_lakh');
            $table->decimal('entry_stock_breadth_pct', 5, 2)->nullable()->after('entry_oi_sentiment');
            $table->decimal('entry_options_flow_pct', 5, 2)->nullable()->after('entry_stock_breadth_pct');
            $table->string('session_window', 20)->nullable()->after('entry_options_flow_pct');
            $table->string('exit_category', 30)->nullable()->after('session_window');

            $table->index('entry_oi_sentiment');
            $table->index('session_window');
            $table->index('exit_category');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('auto_trade_v2_trades', function (Blueprint $table) {
            $table->dropIndex(['entry_oi_sentiment']);
            $table->dropIndex(['session_window']);
            $table->dropIndex(['exit_category']);

            $table->dropColumn([
                'entry_oi_net_diff_pct',
                'entry_oi_net_diff_lakh',
                'entry_oi_sentiment',
                'entry_stock_breadth_pct',
                'entry_options_flow_pct',
                'session_window',
                'exit_category',
            ]);
        });
    }
};
