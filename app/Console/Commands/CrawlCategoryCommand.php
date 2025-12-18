<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Artisan;

class CrawlCategoryCommand extends Command
{
    protected $signature = 'crawl:category {category : The name of the category to crawl}';
    protected $description = 'Crawl specific search category';

    public function handle()
    {
        $category = $this->argument('category');
        $validCategories = config('categories.valid_categories');

        if (!in_array($category, $validCategories)) {
            $this->error("Invalid category: {$category}");
            $this->info("Valid categories are: " . implode(', ', $validCategories));
            return Command::FAILURE;
        }

        $this->info("Triggering crawl for category: {$category}...");

        $exitCode = Artisan::call('crawl:daily', [
            '--category' => $category
        ]);

        if ($exitCode === 0) {
            $this->info("Successfully dispatched crawl jobs for {$category}.");
            return Command::SUCCESS;
        }

        $this->error("Failed to dispatch crawl jobs.");
        return Command::FAILURE;
    }
}
