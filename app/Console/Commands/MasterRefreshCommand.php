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
            $this->info('Running MasterRefreshJob synchronously...');
            MasterRefreshJob::dispatchSync();
            $this->info('✓ Master Refresh Cycle completed.');
        }

        return Command::SUCCESS;
    }
}
