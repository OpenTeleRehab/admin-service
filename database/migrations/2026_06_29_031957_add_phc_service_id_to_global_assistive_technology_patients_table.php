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
        Schema::table('global_assistive_technology_patients', function (Blueprint $table) {
            $table->unsignedBigInteger('phc_service_id')->nullable()->after('clinic_id');
            $table->foreign('phc_service_id')->references('id')->on('phc_services')->onDelete('cascade');
            $table->unsignedBigInteger('clinic_id')->nullable()->change();
            $table->unsignedBigInteger('therapist_id')->nullable()->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('global_assistive_technology_patients', function (Blueprint $table) {
            $table->dropForeign(['phc_service_id']);
            $table->dropColumn('phc_service_id');
            $table->unsignedBigInteger('clinic_id')->nullable(false)->change();
            $table->integer('therapist_id')->nullable(false)->change();
        });
    }
};
