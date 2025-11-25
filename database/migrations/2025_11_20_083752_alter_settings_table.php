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
            $table->dropColumn('attributes');
            $table->string('mfa_enforcement');
            $table->integer('mfa_expiration_duration')->nullable();
            $table->integer('skip_mfa_setup_duration')->nullable();
            $table->string('created_by_role')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('mfa_settings', function (Blueprint $table) {
            $table->json('attributes')->nullable();
            $table->dropColumn(['created_by_role', 'mfa_enforcement', 'mfa_expiration_duration', 'skip_mfa_setup_duration']);
        });
    }
};
