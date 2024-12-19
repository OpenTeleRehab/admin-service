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
        Schema::create('surveys', function (Blueprint $table) {
            $table->id();
            $table->json('organization');
            $table->string('role');
            $table->json('country')->nullable();
            $table->json('location')->nullable();
            $table->json('clinic')->nullable();
            $table->dateTime('start_date')->nullable();
            $table->dateTime('end_date')->nullable();
            $table->integer('frequency');
            $table->boolean('include_at_the_start')->nullable();
            $table->boolean('include_at_the_end')->nullable();
            $table->integer('questionnaire_id');
            $table->string('status');
            $table->dateTime('published_date')->nullable();
            $table->integer('author');
            $table->integer('last_modifier');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('surveys');
    }
};
