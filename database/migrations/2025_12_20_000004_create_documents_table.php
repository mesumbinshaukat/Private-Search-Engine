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
        Schema::create('documents', function (Blueprint $table) {
            $table->id();
            $table->foreignId('url_id')->unique()->constrained('urls')->onDelete('cascade');
            $table->string('title', 500);
            $table->text('description')->nullable();
            $table->string('language', 10)->nullable()->index();
            $table->longText('content')->nullable(); // Full text content
            $table->string('content_hash', 64)->index();
            $table->unsignedInteger('word_count')->default(0);
            $table->json('metadata')->nullable(); // OG tags, Schema.org, etc.
            $table->timestamp('published_at')->nullable();
            $table->timestamp('indexed_at')->index();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('documents');
    }
};
