<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Optimize SQLite for concurrent access on shared hosting
        if (config('database.default') === 'sqlite') {
            try {
                \Illuminate\Support\Facades\DB::statement('PRAGMA journal_mode=WAL;');
                \Illuminate\Support\Facades\DB::statement('PRAGMA synchronous=NORMAL;');
            } catch (\Exception $e) {
                \Illuminate\Support\Facades\Log::warning('Failed to set SQLite PRAGMAs: ' . $e->getMessage());
            }
        }

        // Monitor queue failures to identify stalls
        \Illuminate\Support\Facades\Queue::failing(function (\Illuminate\Queue\Events\JobFailed $event) {
            \Illuminate\Support\Facades\Log::error('Queue Job Failed', [
                'connection' => $event->connectionName,
                'job' => $event->job->resolveName(),
                'exception' => $event->exception->getMessage(),
            ]);
        });
    }
}
