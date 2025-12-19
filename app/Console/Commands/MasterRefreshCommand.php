<?php

namespace App\Console\Commands;

use App\Jobs\MasterRefreshJob;
use Illuminate\Console\Command;

class MasterRefreshCommand extends Command
{
    protected $signature = 'master:refresh {--async : Run in background} {--fresh : Wipe progress and start from zero}';
    protected $description = 'Run the entire search engine refresh cycle (crawl, process, index, upload, cache)';

    public function handle()
    {
        $isFresh = $this->option('fresh');
        $this->info('Triggering Master Refresh Cycle...');

        if ($this->option('async')) {
            MasterRefreshJob::dispatch();
            $this->info('✓ MasterRefreshJob dispatched to queue.');
        } else {
            $this->info('Running Master Refresh Cycle synchronously...');

            if ($isFresh) {
                $this->warn('!!! FRESH START REQUESTED !!!');
                $this->info('Wiping all ParsedRecords and CrawlJobs...');
                \App\Models\ParsedRecord::truncate();
                \App\Models\CrawlJob::truncate();
                $this->info('✓ Database wiped.');
            }

            $maxJobs = env('QUEUE_BATCH_MAX_JOBS', 100);

            $commands = [
                ['name' => 'crawl:daily', 'params' => ['--fresh' => $isFresh]],
                ['name' => 'queue:work', 'params' => ['--stop-when-empty' => true, '--max-jobs' => $maxJobs, '--tries' => 3]],
                ['name' => 'index:generate', 'params' => []],
                ['name' => 'upload:index', 'params' => []],
                ['name' => 'cache:refresh', 'params' => []],
                ['name' => 'queue:status', 'params' => []],
            ];

            foreach ($commands as $index => $cmd) {
                $stepNum = $index + 1;
                $totalSteps = count($commands);
                $this->newLine();
                $this->info(">>> [Step {$stepNum}/{$totalSteps}] Executing: {$cmd['name']}");
                
                $startTime = microtime(true);
                $exitCode = $this->call($cmd['name'], $cmd['params']);
                $duration = round(microtime(true) - $startTime, 2);
                
                if ($exitCode === 0) {
                    $this->info("✓ Step {$cmd['name']} completed in {$duration}s.");
                } else {
                    $this->error("! Step {$cmd['name']} failed with exit code {$exitCode} after {$duration}s.");
                    if (!$isFresh) {
                        $this->warn("Resumable mode: Continuing to next step...");
                    } else {
                        $this->error("Fresh mode: Stopping refresh cycle due to failure.");
                        return Command::FAILURE;
                    }
                }
            }

            $this->newLine();
            $this->info('✓ Master Refresh Cycle finished.');
        }

        return Command::SUCCESS;
    }
}
