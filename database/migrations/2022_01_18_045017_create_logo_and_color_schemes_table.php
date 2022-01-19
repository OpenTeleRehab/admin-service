<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateLogoAndColorSchemesTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('logo_and_color_schemes', function (Blueprint $table) {
            $table->id();
            $table->integer('web_logo')->nullable(true);
            $table->integer('mobile_logo')->nullable(true);
            $table->integer('favicon')->nullable(true);
            $table->string('color')->nullable(true);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('logo_and_color_schemes');
    }
}
