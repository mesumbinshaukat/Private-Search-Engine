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

        // "Poor Man's Cron" - Opportunistic scheduler execution
        // This runs the scheduler on web hits if no real cron is set up
        if (!$this->app->runningInConsole()) {
            $lock = \Illuminate\Support\Facades\Cache::lock('poor-mans-cron', 60);
            if ($lock->get()) {
                try {
                    $startTime = microtime(true);
                    \Illuminate\Support\Facades\Log::debug('Starting Poor Man\'s Cron scheduler execution');
                    
                    \Illuminate\Support\Facades\Artisan::call('schedule:run');
                    
                    $duration = round((microtime(true) - $startTime) * 1000, 2);
                    \Illuminate\Support\Facades\Log::info("Poor Man's Cron executed successfully ({$duration}ms)");
                } catch (\Exception $e) {
                    \Illuminate\Support\Facades\Log::warning('Poor Man\'s Cron failed: ' . $e->getMessage(), [
                        'exception' => get_class($e),
                        'trace' => substr($e->getTraceAsString(), 0, 500)
                    ]);
                } finally {
                    $lock->release();
                }
            }
        }
    }
}
