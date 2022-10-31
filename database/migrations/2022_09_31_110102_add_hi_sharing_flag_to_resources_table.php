<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddHiSharingFlagToResourcesTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        // Remove sharing flag from categories.
        if (Schema::hasColumn('categories', 'hi_only')) {
            Schema::table('categories', function (Blueprint $table) {
                $table->dropColumn('hi_only');
            });
        }

        // Add sharing flag to each resource.
        Schema::table('exercises', function (Blueprint $table) {
            $table->boolean('share_to_hi_library')->default(false);
        });

        Schema::table('education_materials', function (Blueprint $table) {
            $table->boolean('share_to_hi_library')->default(false);
        });

        Schema::table('questionnaires', function (Blueprint $table) {
            $table->boolean('share_to_hi_library')->default(false);
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
            $table->dropColumn('share_to_hi_library');
        });

        Schema::table('education_materials', function (Blueprint $table) {
            $table->dropColumn('share_to_hi_library');
        });

        Schema::table('questionnaires', function (Blueprint $table) {
            $table->dropColumn('share_to_hi_library');
        });
    }
}
