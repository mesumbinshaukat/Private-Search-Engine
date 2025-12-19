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

    public $timeout = 3600; // 1 hour timeout

    public function __construct()
    {
        //
    }

    public function handle(): void
    {
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
                Log::error("MasterRefreshJob failed at step: {$cmd['name']}", [
                    'error' => $e->getMessage()
                ]);
                
                // Continue with next steps if possible, or stop?
                // For critical steps like crawling/indexing, we might want to continue to next categories if it wasn't a total failure
                // But generally if indexing fails, uploading/refreshing might be stale.
            }
        }

        $duration = now()->diffInSeconds($startTime);
        Log::info("--- Master Refresh Cycle Completed in {$duration}s ---");
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
