<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('portfolio_profiles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete()->unique();
            $table->string('headline')->nullable();
            $table->string('tagline')->nullable();
            $table->text('about')->nullable();
            $table->string('location')->nullable();
            $table->string('email')->nullable();
            $table->string('phone')->nullable();
            $table->string('current_role')->nullable();
            $table->string('current_company')->nullable();
            $table->string('portfolio_url')->nullable();
            $table->json('stats')->nullable();
            $table->json('services')->nullable();
            $table->json('experience')->nullable();
            $table->json('skills')->nullable();
            $table->json('education')->nullable();
            $table->json('social')->nullable();
            $table->json('meta')->nullable();
            $table->boolean('is_public')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('portfolio_profiles');
    }
};
