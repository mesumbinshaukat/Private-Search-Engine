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
        Schema::create('parsed_records', function (Blueprint $table) {
            $table->id();
            $table->string('url', 2048)->index();
            $table->string('canonical_url', 2048)->unique();
            $table->string('title', 500);
            $table->text('description')->nullable();
            $table->timestamp('published_at')->nullable();
            $table->enum('category', ['technology', 'business', 'ai', 'sports', 'politics']);
            $table->string('content_hash', 64)->index();
            $table->timestamp('parsed_at');
            $table->timestamps();
            
            $table->index(['category', 'parsed_at']);
            $table->index('created_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('parsed_records');
    }
};
