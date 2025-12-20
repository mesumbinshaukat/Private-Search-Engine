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

        $normalizer = app(\App\Services\UrlNormalizerService::class);

        foreach ($categories as $cat) {
            $today = now()->format('Y-m-d');
            
            if ($isFresh) {
                \Illuminate\Support\Facades\Cache::forget("crawl_count:{$cat}:{$today}");
            }
            
            $seedUrls = config("categories.categories.{$cat}.seed_urls", []);
            
            foreach ($seedUrls as $url) {
                // Normalize seed URL for consistent deduplication
                $normalized = $normalizer->normalize($url);
                $finalUrl = $normalized ? $normalized['normalized'] : $url;

                $existingJob = CrawlJob::where('url', $finalUrl)->where('category', $cat)->first();

                if (!$existingJob || $isFresh) {
                    if ($isFresh && $existingJob) {
                        $existingJob->delete();
                    }

                    $crawlJob = CrawlJob::create([
                        'url' => $finalUrl,
                        'category' => $cat,
                        'status' => CrawlJob::STATUS_PENDING,
                    ]);

                    CrawlPageJob::dispatch($crawlJob);
                    $totalJobsDispatched++;
                } else {
                    $this->info("[-] Seed exists: {$finalUrl} [{$existingJob->status}]");
                }
            }
        }

        $this->info(">>> Dispatched {$totalJobsDispatched} new seed jobs.");
        $pendingCount = \App\Models\CrawlJob::where('status', CrawlJob::STATUS_PENDING)->count();
        $this->info(">>> Total pending jobs in database: {$pendingCount}");

        return Command::SUCCESS;
    }
}
