<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('bids', function (Blueprint $table) {
            $table->id();
            $table->foreignId('bid_workspace_id')->constrained()->cascadeOnDelete();
            $table->string('external_key')->nullable();
            $table->string('project_name');
            $table->string('client_name')->nullable();
            $table->unsignedInteger('total_bids')->nullable();
            $table->decimal('average_bid', 12, 2)->nullable();
            $table->string('average_bid_raw')->nullable();
            $table->decimal('my_bid', 12, 2)->nullable();
            $table->string('my_bid_raw')->nullable();
            $table->string('bid_type')->nullable(); // fixed|hourly
            $table->unsignedInteger('my_rank')->nullable();
            $table->string('status')->default('active'); // active|watching|won|lost|archived
            $table->string('chatting')->nullable();
            $table->timestamp('bid_placed_at')->nullable();
            $table->timestamp('last_synced_at')->nullable();
            $table->string('source')->default('manual'); // manual|screenshot|import
            $table->text('notes')->nullable();
            $table->json('meta')->nullable();
            $table->timestamps();

            $table->unique(['bid_workspace_id', 'project_name']);
            $table->index(['bid_workspace_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('bids');
    }
};
