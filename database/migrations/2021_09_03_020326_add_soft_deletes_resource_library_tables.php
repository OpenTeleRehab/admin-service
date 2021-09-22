<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddSoftDeletesResourceLibraryTables extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('exercises', function (Blueprint $table) {
            $table->softDeletes();
            $table->dropColumn('is_used');
        });

        Schema::table('education_materials', function (Blueprint $table) {
            $table->softDeletes();
            $table->dropColumn('is_used');
        });

        Schema::table('questionnaires', function (Blueprint $table) {
            $table->softDeletes();
            // keep "is_used", for it is not allow to add/modified when it is used
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('exercises', function (Blueprint $table) {
            $table->dropSoftDeletes();
            $table->boolean('is_used')->default(false);
        });

        Schema::table('education_materials', function (Blueprint $table) {
            $table->dropSoftDeletes();
            $table->boolean('is_used')->default(false);
        });

        Schema::table('questionnaires', function (Blueprint $table) {
            $table->dropSoftDeletes();
        });
    }
}
