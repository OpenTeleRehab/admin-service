<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddParentIdAndSuggestedLangToAdditionalFieldsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('additional_fields', function (Blueprint $table) {
            $table->integer('parent_id')->nullable();
            $table->string('suggested_lang')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('additional_fields', function (Blueprint $table) {
            $table->dropColumn('parent_id');
            $table->dropColumn('suggested_lange');
        });
    }
}
