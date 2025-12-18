<?php
// database/migrations/xxxx_xx_xx_create_expired_expiries_table.php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('expired_expiries', function (Blueprint $table) {
            $table->id();
            $table->string('underlying_instrument_key'); // e.g. NSE_INDEX|Nifty 50
            $table->string('underlying_symbol');         // NIFTY
            $table->date('expiry_date')->index();        // from API
            $table->timestamps();

            $table->unique(['underlying_instrument_key', 'expiry_date', 'instrument_type']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('expired_expiries');
    }
};
