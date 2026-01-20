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
        Schema::table('global_patients', function (Blueprint $table) {
            $table->foreign('phc_service_id')->references('id')->on('phc_services')->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('global_patients', function (Blueprint $table) {
            $table->dropForeign(['phc_service_id']);
        });
    }
};
