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
        Schema::table('surveys', function (Blueprint $table) {
            $table->json('region')->nullable()->after('country');
            $table->json('province')->nullable()->after('region');
            $table->json('phc_service')->nullable()->after('clinic');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('surveys', function (Blueprint $table) {
            $table->dropColumn('region');
            $table->dropColumn('province');
            $table->dropColumn('phc_service');
        });
    }
};
