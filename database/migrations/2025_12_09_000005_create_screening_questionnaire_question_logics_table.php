<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateScreeningQuestionnaireQuestionLogicsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('screening_questionnaire_question_logics', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('question_id');
            $table->unsignedBigInteger('target_question_id');
            $table->unsignedBigInteger('target_option_id')->nullable();
            $table->integer('target_option_value')->nullable();
            $table->string('condition_type');
            $table->string('condition_rule');
            $table->timestamps();

            $table->foreign('question_id')->references('id')->on('screening_questionnaire_questions')->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('screening_questionnaire_question_logics');
    }
}
