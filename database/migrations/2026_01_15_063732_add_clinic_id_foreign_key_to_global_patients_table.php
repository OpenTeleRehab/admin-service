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
        DB::table('global_patients')
            ->whereNotNull('clinic_id')
            ->whereNotIn('clinic_id', function ($query) {
                $query->select('id')->from('clinics');
            })->delete();

        Schema::table('global_patients', function (Blueprint $table) {
            $table->unsignedBigInteger('clinic_id')->nullable()->change();
            $table->foreign('clinic_id')->references('id')->on('clinics')->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('global_patients', function (Blueprint $table) {
            $table->dropForeign(['clinic_id']);
            $table->integer('clinic_id')->nullable()->change();
        });
    }
};
