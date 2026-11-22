<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('uploads', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('disk', 40)->default('public');
            $table->string('path');
            $table->string('original_name')->nullable();
            $table->string('mime', 120)->nullable();
            $table->unsignedBigInteger('size')->nullable();
            /** avatar | cover | gallery */
            $table->string('collection', 40);
            $table->nullableMorphs('uploadable');
            $table->unsignedInteger('sort_order')->default(0);
            $table->json('meta')->nullable();
            $table->timestamps();

            $table->index(['uploadable_type', 'uploadable_id', 'collection'], 'uploads_attach_collection_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('uploads');
    }
};
