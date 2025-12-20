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
        Schema::create('hosts', function (Blueprint $table) {
            $table->id();
            $table->string('host', 255)->unique();
            $table->timestamp('robots_fetched_at')->nullable();
            $table->json('crawl_delay')->nullable(); // {user_agent: delay_seconds}
            $table->json('allow_rules')->nullable(); // [{user_agent, patterns}]
            $table->json('disallow_rules')->nullable(); // [{user_agent, patterns}]
            $table->text('robots_txt_raw')->nullable();
            $table->boolean('robots_txt_exists')->default(true);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('hosts');
    }
};
