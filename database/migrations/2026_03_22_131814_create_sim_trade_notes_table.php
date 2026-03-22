<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sim_trade_notes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('position_id')
                  ->constrained('sim_positions')
                  ->cascadeOnDelete();
            $table->string('session_id')->index();
            $table->longText('comment');                  // HTML from Quill editor
            $table->enum('outcome', ['profit', 'stoploss', 'breakeven'])->nullable();
            $table->decimal('exit_price', 10, 2)->nullable();   // price at which note was saved
            $table->integer('exit_qty')->nullable();             // qty exited when note written
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sim_trade_notes');
    }
};
