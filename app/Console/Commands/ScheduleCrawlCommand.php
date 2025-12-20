<?php

namespace App\Console\Commands;

use App\Services\CrawlSchedulerService;
use Illuminate\Console\Command;

class ScheduleCrawlCommand extends Command
{
    protected $signature = 'crawler:schedule 
                            {--reprioritize : Reprioritize all URLs before scheduling}
                            {--cleanup : Clean up stale queue entries}';

    protected $description = 'Schedule URLs for crawling based on priority and freshness';

    public function handle(CrawlSchedulerService $scheduler): int
    {
        $this->info('Starting crawl scheduler...');

        if ($this->option('cleanup')) {
            $this->info('Cleaning up stale queue entries...');
            $cleaned = $scheduler->cleanupStaleQueue();
            $this->info("Cleaned up {$cleaned} stale entries");
        }

        if ($this->option('reprioritize')) {
            $this->info('Reprioritizing all URLs...');
            $updated = $scheduler->reprioritizeAll();
            $this->info("Reprioritized {$updated} URLs");
        }

        $this->info('Scheduling URLs for crawling...');
        $scheduled = $scheduler->schedule();

        $this->info("Scheduled {$scheduled} URLs for crawling");

        return self::SUCCESS;
    }
}
