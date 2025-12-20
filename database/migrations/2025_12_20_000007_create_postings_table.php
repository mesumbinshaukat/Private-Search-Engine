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
        Schema::create('postings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('token_id')->constrained('tokens')->onDelete('cascade');
            $table->foreignId('url_id')->constrained('urls')->onDelete('cascade');
            $table->unsignedInteger('term_frequency')->default(0);
            $table->json('positions')->nullable(); // Array of word positions in document
            $table->timestamps();
            
            // Composite indexes for search queries
            $table->index('token_id');
            $table->index('url_id');
            $table->unique(['token_id', 'url_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('postings');
    }
};
