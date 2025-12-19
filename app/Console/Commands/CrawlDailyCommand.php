<?php

namespace App\Console\Commands;

use App\Jobs\CrawlPageJob;
use App\Models\CrawlJob;
use Illuminate\Console\Command;

class CrawlDailyCommand extends Command
{
    protected $signature = 'crawl:daily {--category=all : Category to crawl}';
    protected $description = 'Trigger daily crawl cycle for all or specific category';

    public function handle()
    {
        $category = $this->option('category');
        $categories = $category === 'all' 
            ? config('categories.valid_categories') 
            : [$category];

        $this->info('Starting daily crawl for categories: ' . implode(', ', $categories));

        // Truncate crawl jobs to start fresh logs for this cycle (SQLite safe)
        if (config('database.default') === 'sqlite') {
            \Illuminate\Support\Facades\DB::table('crawl_jobs')->delete();
        } else {
            \App\Models\CrawlJob::truncate();
        }

        $totalJobs = 0;

        foreach ($categories as $cat) {
            // Reset the daily crawl count cache for each category to allow fresh discovery
            $today = now()->format('Y-m-d');
            \Illuminate\Support\Facades\Cache::forget("crawl_count:{$cat}:{$today}");
            
            $seedUrls = config("categories.categories.{$cat}.seed_urls", []);
            
            foreach ($seedUrls as $url) {
                $crawlJob = CrawlJob::create([
                    'url' => $url,
                    'category' => $cat,
                    'status' => CrawlJob::STATUS_PENDING,
                ]);

                CrawlPageJob::dispatch($crawlJob);
                $totalJobs++;
            }
        }

        $this->info("Dispatched {$totalJobs} crawl jobs");

        return Command::SUCCESS;
    }
}
