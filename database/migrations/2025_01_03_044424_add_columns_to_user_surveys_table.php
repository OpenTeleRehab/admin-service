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
        Schema::table('user_surveys', function (Blueprint $table) {
            $table->integer('treatment_plan_id')->after('survey_id')->nullable();
            $table->string('survey_phase')->after('skipped_at')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('user_surveys', function (Blueprint $table) {
            $table->dropColumn('treatment_plan_id');
            $table->dropColumn('survey_phase');
        });
    }
};
