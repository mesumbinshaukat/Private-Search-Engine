<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('metrics', function (Blueprint $table) {
            $table->id();
            $table->string('metric_type', 100)->index(); // fetch_rate, http_status, queue_backlog, etc.
            $table->string('category', 50)->nullable()->index();
            $table->decimal('value', 15, 4);
            $table->json('metadata')->nullable(); // Additional context
            $table->timestamp('recorded_at')->index();
            $table->timestamps();
            
            // Composite index for time-series queries
            $table->index(['metric_type', 'recorded_at']);
            $table->index(['category', 'metric_type', 'recorded_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('metrics');
    }
};
