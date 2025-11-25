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
        Schema::create('mfa_settings', function (Blueprint $table) {
            $table->id();
            $table->string('role');
            $table->json('organizations')->nullable();
            $table->json('country_ids')->nullable();
            $table->json('clinic_ids')->nullable();
            $table->json('attributes')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('mfa_settings');
    }
};
