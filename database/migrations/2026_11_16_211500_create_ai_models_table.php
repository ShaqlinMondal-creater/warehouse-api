<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ai_models', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id')->index();
            $table->string('label', 80);
            $table->string('provider', 40); // openai|claude
            $table->string('model_id', 120);
            $table->boolean('is_enabled')->default(true);
            $table->boolean('is_default')->default(false);
            $table->date('expires_at')->nullable();
            $table->unsignedInteger('sort_order')->default(0);
            $table->string('notes', 255)->nullable();
            $table->timestamps();

            $table->unique(['user_id', 'provider', 'model_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ai_models');
    }
};
