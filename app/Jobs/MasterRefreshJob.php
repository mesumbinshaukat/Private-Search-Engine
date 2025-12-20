<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Log;

class MasterRefreshJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $timeout;

    public function __construct()
    {
        $this->timeout = config('search.master_refresh_timeout', 3600);
    }

    public function handle(): void
    {
        $lock = \Illuminate\Support\Facades\Cache::lock('master_refresh_lock', 3600);

        if (!$lock->get()) {
            Log::warning('Master Refresh Cycle is already running. Skipping this instance.');
            return;
        }

        try {
            \Illuminate\Support\Facades\Cache::put('master_refresh_running', true, 3600);
            $startTime = now();
            Log::info('--- Starting Master Refresh Cycle ---');

            $commands = [
                ['name' => 'crawl:daily', 'params' => []],
                ['name' => 'queue:work', 'params' => ['--stop-when-empty' => true]],
                ['name' => 'index:generate', 'params' => []],
                ['name' => 'upload:index', 'params' => []],
                ['name' => 'cache:refresh', 'params' => []],
                ['name' => 'queue:status', 'params' => []],
            ];

            foreach ($commands as $cmd) {
                try {
                    $this->runCommand($cmd['name'], $cmd['params']);
                } catch (\Exception $e) {
                    Log::error("MasterRefreshJob encountered an error at step: {$cmd['name']}", [
                        'error' => $e->getMessage()
                    ]);
                }
            }

            $duration = now()->diffInSeconds($startTime);
            Log::info("--- Master Refresh Cycle Completed in {$duration}s ---");
        } finally {
            \Illuminate\Support\Facades\Cache::forget('master_refresh_running');
            $lock->release();
        }
    }

    private function runCommand(string $command, array $params): void
    {
        Log::info("Running artisan command: {$command}", $params);
        
        $exitCode = Artisan::call($command, $params);
        $output = Artisan::output();

        if ($exitCode === 0) {
            Log::info("✓ Command {$command} completed successfully");
        } else {
            Log::error("✗ Command {$command} failed with exit code: {$exitCode}", [
                'output' => $output
            ]);
            throw new \Exception("Artisan command {$command} failed.");
        }
    }
}
