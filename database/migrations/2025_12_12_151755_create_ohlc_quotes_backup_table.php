<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up()
    {
        DB::statement('CREATE TABLE IF NOT EXISTS ohlc_quotes_backup LIKE ohlc_quotes');
    }

    public function down()
    {
        DB::statement('DROP TABLE IF EXISTS ohlc_quotes_backup');
    }
};
