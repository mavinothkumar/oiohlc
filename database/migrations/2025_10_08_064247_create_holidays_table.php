<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('holidays', function (Blueprint $table) {
            $table->id();
            $table->string('holiday_type'); // Trading, Clearing, Settlement, etc.
            $table->string('description');
            $table->timestamps();

            $table->date('date')->index();
            $table->string('exchange')->index();

        });
    }

    public function down(): void
    {
        Schema::dropIfExists('holidays');
    }
};

