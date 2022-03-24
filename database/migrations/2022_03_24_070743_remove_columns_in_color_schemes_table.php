<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class RemoveColumnsInColorSchemesTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('color_schemes', function (Blueprint $table) {
            $table->dropColumn('primary_text_color');
            $table->dropColumn('secondary_text_color');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('color_schemes', function (Blueprint $table) {
            $table->string('primary_text_color');
            $table->string('secondary_text_color');
        });
    }
}
