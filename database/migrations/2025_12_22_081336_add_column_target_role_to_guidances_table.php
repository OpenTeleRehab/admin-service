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
        Schema::table('guidances', function (Blueprint $table) {
            $table->enum('target_role', ['therapist', 'phc_worker'])->default('therapist');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('guidances', function (Blueprint $table) {
            $table->dropColumn('target_role');
        });
    }
};
