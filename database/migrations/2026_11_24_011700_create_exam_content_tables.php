<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('exam_content_chapters', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('exam_subject_id')->constrained('exam_subjects')->cascadeOnDelete();
            $table->string('code', 40);
            $table->string('title')->nullable();
            $table->string('status', 40)->nullable();
            $table->unsignedInteger('mcq_used')->nullable();
            $table->unsignedInteger('mcq_target')->nullable();
            $table->json('meta')->nullable();
            $table->string('content_hash', 64)->nullable();
            $table->timestamps();

            $table->unique(['user_id', 'exam_subject_id', 'code'], 'exam_content_chapters_user_subject_code');
            $table->index(['exam_subject_id', 'code']);
        });

        Schema::create('exam_content_questions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('exam_subject_id')->constrained('exam_subjects')->cascadeOnDelete();
            $table->string('external_id', 80);
            $table->string('set_key', 80)->nullable();
            $table->unsignedInteger('question_no')->nullable();
            $table->string('chapter_code', 40)->nullable();
            $table->text('stem');
            $table->json('options');
            $table->string('correct_answer', 1)->nullable();
            $table->text('explanation')->nullable();
            $table->string('source_questions_file')->nullable();
            $table->string('source_answers_file')->nullable();
            $table->string('content_hash', 64)->nullable();
            $table->timestamps();

            $table->unique(['user_id', 'exam_subject_id', 'external_id'], 'exam_content_questions_user_subject_ext');
            $table->index(['user_id', 'exam_subject_id', 'set_key']);
            $table->index(['user_id', 'exam_subject_id', 'chapter_code']);
        });

        Schema::create('exam_content_notes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('exam_subject_id')->constrained('exam_subjects')->cascadeOnDelete();
            $table->string('relative_path', 255);
            $table->string('title')->nullable();
            $table->longText('body');
            $table->string('content_hash', 64)->nullable();
            $table->timestamps();

            $table->unique(['user_id', 'exam_subject_id', 'relative_path'], 'exam_content_notes_user_subject_path');
            $table->index(['exam_subject_id', 'relative_path']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('exam_content_notes');
        Schema::dropIfExists('exam_content_questions');
        Schema::dropIfExists('exam_content_chapters');
    }
};
