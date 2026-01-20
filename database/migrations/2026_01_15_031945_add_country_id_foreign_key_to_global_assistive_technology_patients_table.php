<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        DB::table('global_assistive_technology_patients')
            ->whereNotIn('country_id', function ($query) {
                $query->select('id')->from('countries');
            })
            ->delete();

        Schema::table('global_assistive_technology_patients', function (Blueprint $table) {
            $table->unsignedBigInteger('country_id')->change();
            $table->foreign('country_id')->references('id')->on('countries')->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('global_assistive_technology_patients', function (Blueprint $table) {
            $table->dropForeign(['country_id']);
            $table->integer('country_id')->change();
        });
    }
};
