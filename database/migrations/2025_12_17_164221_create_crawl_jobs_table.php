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
        Schema::create('crawl_jobs', function (Blueprint $table) {
            $table->id();
            $table->string('url', 2048)->index();
            $table->enum('category', ['technology', 'business', 'ai', 'sports', 'politics']);
            $table->enum('status', ['pending', 'processing', 'completed', 'failed'])->default('pending');
            $table->integer('http_status')->nullable();
            $table->boolean('robots_txt_allowed')->default(true);
            $table->timestamp('crawled_at')->nullable();
            $table->text('failed_reason')->nullable();
            $table->timestamps();
            
            $table->index(['category', 'status']);
            $table->index('created_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('crawl_jobs');
    }
};
