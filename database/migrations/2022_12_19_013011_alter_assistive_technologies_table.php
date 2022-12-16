<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AlterAssistiveTechnologiesTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('assistive_technologies', function (Blueprint $table) {
            $table->integer('file_id')->nullable()->change();
            $table->dateTime('deleted_at')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('assistive_technologies', function (Blueprint $table) {
            $table->integer('file_id')->nullable(false)->change();
            $table->dropColumn('deleted_at');
        });
    }
}
