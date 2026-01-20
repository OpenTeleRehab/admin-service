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
        Schema::table('mfa_settings', function (Blueprint $table) {
            $table->json('region_ids')->nullable();
            $table->json('phc_service_ids')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('mfa_settings', function (Blueprint $table) {
            $table->dropColumn(['region_ids', 'phc_service_ids']);
        });
    }
};
