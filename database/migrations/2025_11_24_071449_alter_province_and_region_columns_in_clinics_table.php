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
        Schema::table('clinics', function (Blueprint $table) {
            $table->dropColumn('province');
            $table->dropColumn('region');
            $table->unsignedBigInteger('province_id')->nullable();
            $table->unsignedBigInteger('region_id')->nullable();

            $table->foreign('province_id')->references('id')->on('provinces')->onDelete('set null');
            $table->foreign('region_id')->references('id')->on('regions')->onDelete('set null');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('clinics', function (Blueprint $table) {
            $table->dropForeign(['province_id']);
            $table->dropForeign(['region_id']);
            $table->dropColumn('province_id');
            $table->dropColumn('region_id');
            $table->string('province')->nullable();
            $table->string('region')->nullable();
        });
    }
};
