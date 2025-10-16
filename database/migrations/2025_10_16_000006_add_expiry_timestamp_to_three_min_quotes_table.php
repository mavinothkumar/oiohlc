<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddExpiryTimestampToThreeMinQuotesTable extends Migration
{
    /**
     * Run the migrations.
     */
    public function up()
    {
        Schema::table('three_min_quotes', function (Blueprint $table) {
            $table->unsignedBigInteger('expiry_timestamp')->nullable()->after('expiry_date');
            $table->index('expiry_timestamp', 'three_min_quotes_expiry_timestamp_index');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down()
    {
        Schema::table('three_min_quotes', function (Blueprint $table) {
            $table->dropIndex('three_min_quotes_expiry_timestamp_index');
            $table->dropColumn('expiry_timestamp');
        });
    }
}
