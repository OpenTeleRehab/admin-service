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
        Schema::table('activity_log', function (Blueprint $table) {
            $table->string('full_name')->nullable();
            $table->string('group')->nullable();
            $table->integer('country_id')->nullable();
            $table->integer('clinic_id')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('activity_log', function (Blueprint $table) {
            $table->dropColumn('full_name');
            $table->dropColumn('group');
            $table->dropColumn('country_id');
            $table->dropColumn('clinic_id');
        });
    }
};
