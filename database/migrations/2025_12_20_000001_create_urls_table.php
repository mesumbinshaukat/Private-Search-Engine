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
        Schema::create('urls', function (Blueprint $table) {
            $table->id();
            $table->string('normalized_url', 2048)->unique();
            $table->string('original_url', 2048);
            $table->string('host', 255)->index();
            $table->text('path')->nullable();
            $table->string('query_hash', 64)->nullable();
            $table->unsignedTinyInteger('depth')->default(0)->index();
            $table->unsignedTinyInteger('priority')->default(50)->index();
            $table->enum('status', ['pending', 'crawled', 'failed', 'skipped'])->default('pending')->index();
            $table->timestamp('last_crawled_at')->nullable()->index();
            $table->timestamp('next_crawl_at')->nullable()->index();
            $table->unsignedSmallInteger('http_status')->nullable();
            $table->string('content_hash', 64)->nullable()->index();
            $table->enum('category', ['technology', 'business', 'ai', 'sports', 'politics'])->nullable()->index();
            $table->text('failed_reason')->nullable();
            $table->unsignedTinyInteger('retry_count')->default(0);
            $table->timestamps();
            
            // Composite indexes for common queries
            $table->index(['status', 'next_crawl_at']);
            $table->index(['category', 'status']);
            $table->index(['host', 'status']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('urls');
    }
};
