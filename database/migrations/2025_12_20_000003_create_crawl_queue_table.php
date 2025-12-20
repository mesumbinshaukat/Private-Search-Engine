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
        Schema::create('crawl_queue', function (Blueprint $table) {
            $table->id();
            $table->foreignId('url_id')->constrained('urls')->onDelete('cascade');
            $table->timestamp('scheduled_at')->index();
            $table->timestamp('locked_at')->nullable()->index();
            $table->string('worker_id', 64)->nullable();
            $table->timestamps();
            
            // Composite index for queue processing
            $table->index(['scheduled_at', 'locked_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('crawl_queue');
    }
};
