<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sim_positions', function (Blueprint $table) {
            $table->string('strategy')->nullable()->after('status');
        });
    }

    public function down(): void
    {

    }
};
