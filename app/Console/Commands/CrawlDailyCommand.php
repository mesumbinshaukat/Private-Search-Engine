<?php

namespace App\Console\Commands;

use App\Jobs\CrawlPageJob;
use App\Models\CrawlJob;
use Illuminate\Console\Command;

class CrawlDailyCommand extends Command
{
    protected $signature = 'crawl:daily {--category=all : Category} {--fresh : Wipe current jobs and start from zero}';
    protected $description = 'Trigger daily crawl cycle (supports resuming by default)';

    public function handle()
    {
        $category = $this->option('category');
        $isFresh = $this->option('fresh');
        $categories = $category === 'all' 
            ? config('categories.valid_categories') 
            : [$category];

        if ($isFresh) {
            $this->warn('Performing a FRESH crawl. Wiping existing job logs for categories...');
            \App\Models\CrawlJob::whereIn('category', $categories)->delete();
        } else {
            $this->info('Resuming crawl cycle. Existing progress will be preserved.');
        }

        $maxCrawls = config('crawler.max_crawls_per_category', 10);
        $this->info(">>> Crawl Limit: {$maxCrawls} pages per category/day.");

        $totalJobsDispatched = 0;

        foreach ($categories as $cat) {
            $today = now()->format('Y-m-d');
            
            if ($isFresh) {
                \Illuminate\Support\Facades\Cache::forget("crawl_count:{$cat}:{$today}");
            }
            
            $seedUrls = config("categories.categories.{$cat}.seed_urls", []);
            
            foreach ($seedUrls as $url) {
                $existingJob = CrawlJob::where('url', $url)->where('category', $cat)->first();

                if (!$existingJob || $isFresh) {
                    if ($isFresh && $existingJob) {
                        $existingJob->delete();
                    }

                    $crawlJob = CrawlJob::create([
                        'url' => $url,
                        'category' => $cat,
                        'status' => CrawlJob::STATUS_PENDING,
                    ]);

                    CrawlPageJob::dispatch($crawlJob);
                    $totalJobsDispatched++;
                } else {
                    $this->info("[-] Seed exists: {$url} [{$existingJob->status}]");
                    
                    // If it was pending, it might have been lost from the queue (worker crash)
                    // We don't re-dispatch here to avoid ballooning the queue, 
                    // as the scheduler/master:refresh will handle the queue work anyway.
                    // But we could optionally re-dispatch if it's been pending for too long.
                }
            }
        }

        $this->info(">>> Dispatched {$totalJobsDispatched} new seed jobs.");
        $pendingCount = \App\Models\CrawlJob::where('status', CrawlJob::STATUS_PENDING)->count();
        $this->info(">>> Total pending jobs in database: {$pendingCount}");

        return Command::SUCCESS;
    }
}
