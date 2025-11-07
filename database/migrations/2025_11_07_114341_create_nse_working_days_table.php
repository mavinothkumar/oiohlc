<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateNseWorkingDaysTable extends Migration
{
    public function up()
    {
        Schema::create('nse_working_days', function (Blueprint $table) {
            $table->id();
            $table->date('working_date')->unique();
            $table->boolean('previous')->default(0);
            $table->boolean('current')->default(0);
            $table->timestamps();
        });
    }

    public function down()
    {
        Schema::dropIfExists('nse_working_days');
    }
}
