<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('exam_practice_attempts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('exam_subject_id')->constrained('exam_subjects')->cascadeOnDelete();
            $table->string('set_key', 80); // e.g. DAY-01
            $table->string('questions_file')->nullable();
            $table->unsignedInteger('total')->default(0);
            $table->unsignedInteger('answered')->default(0);
            $table->unsignedInteger('correct')->default(0);
            $table->unsignedInteger('incorrect')->default(0);
            $table->decimal('accuracy_percent', 5, 2)->nullable();
            $table->json('answers')->nullable(); // qNo => selected option
            $table->json('results')->nullable(); // graded rows summary
            $table->timestamp('started_at')->nullable();
            $table->timestamp('submitted_at')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'exam_subject_id', 'set_key']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('exam_practice_attempts');
    }
};
