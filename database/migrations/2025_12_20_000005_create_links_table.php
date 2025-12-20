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
        Schema::create('links', function (Blueprint $table) {
            $table->id();
            $table->foreignId('from_url_id')->constrained('urls')->onDelete('cascade');
            $table->foreignId('to_url_id')->constrained('urls')->onDelete('cascade');
            $table->boolean('nofollow')->default(false);
            $table->string('anchor_text', 500)->nullable();
            $table->timestamps();
            
            // Indexes for graph traversal
            $table->index('from_url_id');
            $table->index('to_url_id');
            $table->unique(['from_url_id', 'to_url_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('links');
    }
};
