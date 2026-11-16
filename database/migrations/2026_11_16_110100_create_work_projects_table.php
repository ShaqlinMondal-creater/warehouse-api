<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('work_projects', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('title');
            $table->string('slug');
            $table->string('summary')->nullable();
            $table->text('description')->nullable();
            $table->string('role')->nullable();
            $table->json('stack')->nullable();
            $table->string('platform_or_client')->nullable();
            $table->string('status')->default('done'); // done|ongoing|paused
            $table->date('started_on')->nullable();
            $table->date('ended_on')->nullable();
            $table->string('url')->nullable();
            $table->string('repo_url')->nullable();
            $table->string('cover_path')->nullable();
            $table->unsignedInteger('sort_order')->default(0);
            $table->boolean('is_public')->default(true);
            $table->json('meta')->nullable();
            $table->timestamps();

            $table->unique(['user_id', 'slug']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('work_projects');
    }
};
