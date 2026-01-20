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
        Schema::table('phc_services', function (Blueprint $table) {
            $table->dropForeign(['province_id']);
            $table->foreign('province_id')
                ->references('id')
                ->on('provinces')
                ->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('phc_services', function (Blueprint $table) {
            $table->dropForeign(['province_id']);
            $table->foreign('province_id')
                ->references('id')
                ->on('provinces')
                ->onDelete('restrict');
        });
    }
};
