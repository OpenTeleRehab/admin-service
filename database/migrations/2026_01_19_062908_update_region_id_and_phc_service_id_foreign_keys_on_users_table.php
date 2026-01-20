<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropForeign(['region_id']);
            $table->dropForeign(['phc_service_id']);
            $table->unsignedBigInteger('clinic_id')->nullable()->change();
            $table->foreign('region_id')->references('id')->on('regions')->onDelete('cascade');
            $table->foreign('phc_service_id')->references('id')->on('phc_services')->onDelete('cascade');
            $table->foreign('clinic_id')->references('id')->on('clinics')->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropForeign(['region_id']);
            $table->dropForeign(['phc_service_id']);
            $table->dropForeign(['clinic_id']);
            $table->string('clinic_id')->nullable()->change();
            $table->foreign('region_id')->references('id')->on('regions')->onDelete('set null');
            $table->foreign('phc_service_id')->references('id')->on('phc_services')->onDelete('restrict');
        });
    }
};
