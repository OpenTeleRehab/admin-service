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
        Schema::table('global_treatment_plans', function (Blueprint $table) {
            $table->integer('health_condition_id')->nullable();
            $table->integer('health_condition_group_id')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('global_treatment_plans', function (Blueprint $table) {
            $table->dropColumn('health_condition_id');
            $table->dropColumn('health_condition_group_id');
        });
    }
};
