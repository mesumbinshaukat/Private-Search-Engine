<?php

namespace App\Console\Commands;

use App\Services\IndexerService;
use Illuminate\Console\Command;

class GenerateIndexCommand extends Command
{
    protected $signature = 'index:generate {--category=all : Category to generate index for}';
    protected $description = 'Generate index JSON files for all or specific category';

    public function handle(IndexerService $indexer)
    {
        $category = $this->option('category');
        $categories = $category === 'all' 
            ? config('categories.valid_categories') 
            : [$category];

        $this->info('Generating indexes for categories: ' . implode(', ', $categories));

        $results = [];

        foreach ($categories as $cat) {
            $this->info("Generating index for {$cat}...");
            
            $result = $indexer->generateIndex($cat);
            $results[$cat] = $result;

            if ($result['success']) {
                $this->info("✓ {$cat}: {$result['record_count']} records");
            } else {
                $this->error("✗ {$cat}: {$result['error']}");
            }
        }

        $this->info('Cleaning up old records...');
        $deleted = $indexer->cleanupOldRecords();
        $this->info("Deleted {$deleted} old records");

        return Command::SUCCESS;
    }
}
