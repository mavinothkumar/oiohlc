<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sim_trade_notes', function (Blueprint $table) {
            $table->text('comment')->nullable()->change();
            $table->string('strategy')->nullable()->after('outcome');  // add if not exists
        });
    }

    public function down(): void
    {

    }
};
