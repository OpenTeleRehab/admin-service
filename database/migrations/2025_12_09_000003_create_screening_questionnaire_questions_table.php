<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateScreeningQuestionnaireQuestionsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('screening_questionnaire_questions', function (Blueprint $table) {
            $table->id();
            $table->json('question_text');
            $table->string('question_type');
            $table->boolean('mandatory')->default(0);
            $table->integer('order');
            $table->unsignedBigInteger('questionnaire_id');
            $table->unsignedBigInteger('section_id')->nullable();
            $table->unsignedBigInteger('file_id')->nullable();
            $table->json('auto_translated')->nullable();
            $table->timestamps();

            $table->foreign('questionnaire_id')->references('id')->on('screening_questionnaires')->onDelete('cascade');
            $table->foreign('section_id')->references('id')->on('screening_questionnaire_sections')->onDelete('cascade');
            $table->foreign('file_id')->references('id')->on('files')->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('screening_questionnaire_questions');
    }
}
