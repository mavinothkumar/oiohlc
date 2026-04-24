<?php

// database/migrations/xxxx_create_backtest_trades_table.php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::table('backtest_trades', function (Blueprint $table) {
            $table->dateTime('signal_time')->nullable()->after('entry_time')
                  ->comment('Time when breakout signal was confirmed (FCB strategy)');
        });
    }
};
