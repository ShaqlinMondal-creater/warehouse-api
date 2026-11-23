<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('exam_sets', function (Blueprint $table) {
            $table->id();
            $table->string('slug', 80)->unique();
            $table->string('title');
            $table->unsignedSmallInteger('year')->nullable();
            $table->boolean('is_enabled')->default(true);
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
        });

        Schema::create('exam_subjects', function (Blueprint $table) {
            $table->id();
            $table->foreignId('exam_set_id')->constrained('exam_sets')->cascadeOnDelete();
            $table->string('slug', 80);
            $table->string('title');
            $table->boolean('is_enabled')->default(true);
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();

            $table->unique(['exam_set_id', 'slug']);
        });

        /** Per-user extracted pack state (content stays on disk, not in rows). */
        Schema::create('exam_subject_states', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('exam_subject_id')->constrained('exam_subjects')->cascadeOnDelete();
            $table->string('storage_path')->nullable();
            $table->timestamp('last_synced_at')->nullable();
            $table->string('last_original_name')->nullable();
            $table->unsignedBigInteger('last_bytes')->nullable();
            $table->unsignedInteger('file_count')->default(0);
            $table->json('progress')->nullable();
            $table->timestamps();

            $table->unique(['user_id', 'exam_subject_id']);
        });

        Schema::create('exam_syncs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('exam_subject_id')->constrained('exam_subjects')->cascadeOnDelete();
            $table->string('original_name');
            $table->unsignedBigInteger('bytes')->default(0);
            $table->string('status', 20); // ok | failed
            $table->string('storage_path')->nullable();
            $table->unsignedInteger('files_indexed')->default(0);
            $table->text('message')->nullable();
            $table->json('progress')->nullable();
            $table->timestamp('synced_at')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'exam_subject_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('exam_syncs');
        Schema::dropIfExists('exam_subject_states');
        Schema::dropIfExists('exam_subjects');
        Schema::dropIfExists('exam_sets');
    }
};
