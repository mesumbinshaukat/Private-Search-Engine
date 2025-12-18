<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class ClearQueueCommand extends Command
{
    protected $signature = 'queue:clear {--force : Skip confirmation}';
    protected $description = 'Clear all pending jobs from the queue';

    public function handle()
    {
        if (!$this->option('force')) {
            if (!$this->confirm('This will delete all pending jobs from the queue. Are you sure?')) {
                $this->info('Queue clear cancelled.');
                return Command::SUCCESS;
            }
        }

        $deleted = DB::table('jobs')->delete();
        
        $this->info("Cleared {$deleted} pending jobs from the queue.");
        
        // Also clear failed jobs if user wants
        if ($this->confirm('Do you also want to clear failed jobs?', false)) {
            $failedDeleted = DB::table('failed_jobs')->delete();
            $this->info("Cleared {$failedDeleted} failed jobs.");
        }

        return Command::SUCCESS;
    }
}
