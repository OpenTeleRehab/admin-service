<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        DB::table('global_treatment_plans')
            ->whereNotIn('country_id', function ($query) {
                $query->select('id')->from('countries');
            })
            ->delete();

        Schema::table('global_treatment_plans', function (Blueprint $table) {
            $table->unsignedBigInteger('country_id')->change();
            $table->foreign('country_id')->references('id')->on('countries')->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('global_treatment_plans', function (Blueprint $table) {
            $table->dropForeign(['country_id']);
            $table->integer('country_id')->nullable()->change();
        });
    }
};
