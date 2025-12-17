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
        Schema::create('index_metadata', function (Blueprint $table) {
            $table->id();
            $table->enum('category', ['technology', 'business', 'ai', 'sports', 'politics']);
            $table->date('date')->index();
            $table->integer('record_count');
            $table->string('file_path', 500);
            $table->string('google_drive_file_id', 100)->nullable();
            $table->string('checksum', 64);
            $table->timestamp('uploaded_at')->nullable();
            $table->timestamps();
            
            $table->unique(['category', 'date']);
            $table->index('created_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('index_metadata');
    }
};
