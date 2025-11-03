<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('job_trackers', function (Blueprint $table) {
            $table->uuid('job_id')->primary();
            $table->string('status')->default('queued');
            $table->nullableMorphs('trackable');
            $table->text('message')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('job_trackers');
    }
};
