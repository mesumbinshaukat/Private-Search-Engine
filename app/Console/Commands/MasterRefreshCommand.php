<?php

namespace App\Console\Commands;

use App\Jobs\MasterRefreshJob;
use Illuminate\Console\Command;

class MasterRefreshCommand extends Command
{
    protected $signature = 'master:refresh {--async : Run the refresh cycle asynchronously}';
    protected $description = 'Run the entire search engine refresh cycle (crawl, process, index, upload, cache)';

    public function handle()
    {
        $this->info('Triggering Master Refresh Cycle...');

        if ($this->option('async')) {
            MasterRefreshJob::dispatch();
            $this->info('✓ MasterRefreshJob dispatched to queue.');
        } else {
            $this->info('Running Master Refresh Cycle synchronously...');

            $commands = [
                ['name' => 'crawl:daily', 'params' => []],
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
                    $this->error("✗ Step {$cmd['name']} failed. Aborting refresh.");
                    return Command::FAILURE;
                }
            }

            $this->newLine();
            $this->info('✓ Master Refresh Cycle completed successfully.');
        }

        return Command::SUCCESS;
    }
}
