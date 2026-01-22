<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->boolean('notifiable')->default(false);
        });

        /**
         * Assign notifiable to users type clinic_admin and phc_service_admin
         */
        DB::table('users')->whereIn('type', ['clinic_admin', 'phc_service_admin'])->update(['notifiable' => 1]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('notifiable');
        });
    }
};
