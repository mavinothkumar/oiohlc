<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('option_chains_history')) {
            DB::statement('CREATE TABLE option_chains_history LIKE option_chains');
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('option_chains_history');
    }
};
