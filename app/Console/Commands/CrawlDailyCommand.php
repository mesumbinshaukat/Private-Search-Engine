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

        $totalJobsDispatched = 0;

        foreach ($categories as $cat) {
            $today = now()->format('Y-m-d');
            
            if ($isFresh) {
                \Illuminate\Support\Facades\Cache::forget("crawl_count:{$cat}:{$today}");
            }
            
            $seedUrls = config("categories.categories.{$cat}.seed_urls", []);
            
            foreach ($seedUrls as $url) {
                // Check if this seed is already in the system (pending or completed)
                $exists = CrawlJob::where('url', $url)->where('category', $cat)->exists();

                if (!$exists || $isFresh) {
                    $crawlJob = CrawlJob::create([
                        'url' => $url,
                        'category' => $cat,
                        'status' => CrawlJob::STATUS_PENDING,
                    ]);

                    CrawlPageJob::dispatch($crawlJob);
                    $totalJobsDispatched++;
                }
            }
        }

        $this->info("Dispatched {$totalJobsDispatched} new seed jobs.");

        return Command::SUCCESS;
    }
}
