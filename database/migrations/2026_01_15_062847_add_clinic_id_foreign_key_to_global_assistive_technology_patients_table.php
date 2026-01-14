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
            ->whereNotIn('clinic_id', function ($query) {
                $query->select('id')->from('clinics');
            })->delete();

        Schema::table('global_assistive_technology_patients', function (Blueprint $table) {
            $table->unsignedBigInteger('clinic_id')->change();
            $table->foreign('clinic_id')->references('id')->on('clinics')->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('global_assistive_technology_patients', function (Blueprint $table) {
            Schema::table('global_assistive_technology_patients', function (Blueprint $table) {
                $table->integer('clinic_id')->change();
                $table->dropForeign(['clinic_id']);
            });
        });
    }
};
