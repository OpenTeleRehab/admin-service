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
            $table->string('mfa_expiration_unit')->nullable();
            $table->string('skip_mfa_setup_unit')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('mfa_settings', function (Blueprint $table) {
            $table->dropColumn(['mfa_expiration_unit', 'skip_mfa_setup_unit']);
        });
    }
};
