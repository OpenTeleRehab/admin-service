<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateScreeningQuestionnaireQuestionOptionsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('screening_questionnaire_question_options', function (Blueprint $table) {
            $table->id();
            $table->json('option_text');
            $table->integer('option_point')->nullable();
            $table->integer('threshold')->nullable();
            $table->integer('min')->nullable();
            $table->integer('max')->nullable();
            $table->unsignedBigInteger('question_id');
            $table->unsignedBigInteger('file_id')->nullable();
            $table->json('auto_translated')->nullable();
            $table->timestamps();

            $table->foreign('question_id')->references('id')->on('screening_questionnaire_questions')->onDelete('cascade');
            $table->foreign('file_id')->references('id')->on('files');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('screening_questionnaire_question_options');
    }
}
