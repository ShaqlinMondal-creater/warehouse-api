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

            $table->unique(['user_id', 'exam_subject_id', 'code'], 'ec_chapters_user_subj_code_uq');
            $table->index(['exam_subject_id', 'code'], 'ec_chapters_subj_code_idx');
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

            $table->unique(['user_id', 'exam_subject_id', 'external_id'], 'ec_questions_user_subj_ext_uq');
            $table->index(['user_id', 'exam_subject_id', 'set_key'], 'ec_questions_user_subj_set_idx');
            $table->index(['user_id', 'exam_subject_id', 'chapter_code'], 'ec_questions_user_subj_ch_idx');
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

            $table->unique(['user_id', 'exam_subject_id', 'relative_path'], 'ec_notes_user_subj_path_uq');
            $table->index(['exam_subject_id', 'relative_path'], 'ec_notes_subj_path_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('exam_content_notes');
        Schema::dropIfExists('exam_content_questions');
        Schema::dropIfExists('exam_content_chapters');
    }
};
