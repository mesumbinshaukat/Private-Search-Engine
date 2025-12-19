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

            $commands = [
                ['name' => 'crawl:daily', 'params' => ['--fresh' => $isFresh]],
                ['name' => 'queue:work', 'params' => ['--stop-when-empty' => true]],
                ['name' => 'index:generate', 'params' => []],
                ['name' => 'upload:index', 'params' => []],
                ['name' => 'cache:refresh', 'params' => []],
                ['name' => 'queue:status', 'params' => []],
            ];

            foreach ($commands as $cmd) {
                $this->newLine();
                $this->info(">>> Executing Step: {$cmd['name']}");
                
                $exitCode = $this->call($cmd['name'], $cmd['params']);
                
                if ($exitCode !== 0) {
                    $this->warn("! Step {$cmd['name']} reported a non-zero exit code ({$exitCode}). Continuing anyway...");
                }
            }

            $this->newLine();
            $this->info('✓ Master Refresh Cycle completed successfully.');
        }

        return Command::SUCCESS;
    }
}
