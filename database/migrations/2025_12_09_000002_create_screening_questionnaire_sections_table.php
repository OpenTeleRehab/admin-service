<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateScreeningQuestionnaireSectionsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('screening_questionnaire_sections', function (Blueprint $table) {
            $table->id();
            $table->json('title');
            $table->json('description');
            $table->integer('order');
            $table->unsignedBigInteger('questionnaire_id');
            $table->json('auto_translated')->nullable();
            $table->timestamps();

            $table->foreign('questionnaire_id')->references('id')->on('screening_questionnaires')->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('screening_questionnaire_sections');
    }
}
