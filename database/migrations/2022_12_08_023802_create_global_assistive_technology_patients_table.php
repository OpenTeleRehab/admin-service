<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateGlobalAssistiveTechnologyPatientsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('global_assistive_technology_patients', function (Blueprint $table) {
            $table->id();
            $table->integer('patient_id');
            $table->string('gender');
            $table->dateTime('date_of_birth')->nullable();
            $table->string('identity')->nullable();
            $table->integer('clinic_id');
            $table->integer('country_id');
            $table->integer('therapist_id');
            $table->integer('assistive_technology_id');
            $table->dateTime('provision_date');
            $table->boolean('enabled')->default(0);
            $table->dateTime('deleted_at')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('global_assistive_technology_patients');
    }
}
