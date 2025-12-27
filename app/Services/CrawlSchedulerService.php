<?php

namespace App\Services;

use App\Models\Url;
use App\Models\CrawlQueue;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class CrawlSchedulerService
{
    /**
     * Schedule URLs for crawling based on priority and freshness.
     */
    public function schedule(): int
    {
        $scheduled = 0;
        
        // Get URLs that are due for crawling
        $urls = Url::dueForCrawl()
            ->where('status', '!=', 'skipped')
            ->orderBy('priority', 'desc')
            ->orderBy('next_crawl_at')
            ->limit(config('crawler.fetch_batch_size', 100))
            ->get();

        foreach ($urls as $url) {
            // Check if already in queue
            if (CrawlQueue::where('url_id', $url->id)->exists()) {
                continue;
            }

            // Add to queue
            CrawlQueue::create([
                'url_id' => $url->id,
                'scheduled_at' => now(),
            ]);

            $scheduled++;
        }

        Log::info('Scheduled URLs for crawling', ['count' => $scheduled]);

        return $scheduled;
    }

    /**
     * Calculate priority for a URL based on various factors.
     */
    public function calculatePriority(Url $url): int
    {
        $priority = 50; // Base priority

        // Depth factor (lower depth = higher priority)
        $depthBonus = max(0, 50 - ($url->depth * 10));
        $priority += $depthBonus;

        // Inbound links factor
        $inboundCount = $url->inboundLinks()->count();
        $linkBonus = min(30, $inboundCount * 5);
        $priority += $linkBonus;

        // Freshness factor (recently crawled = lower priority)
        if ($url->last_crawled_at) {
            $daysSinceCrawl = $url->last_crawled_at->diffInDays(now());
            if ($daysSinceCrawl < 1) {
                $priority -= 20;
            } elseif ($daysSinceCrawl > 7) {
                $priority += 10;
            }
        } else {
            // Never crawled = high priority
            $priority += 20;
        }

        // Ensure priority is within bounds
        return max(1, min(100, $priority));
    }

    /**
     * Calculate next crawl time based on URL characteristics.
     */
    public function calculateNextCrawl(Url $url): \DateTime
    {
        // Adaptive intervals based on depth
        $intervals = [
            0 => 1,   // Seed URLs: daily
            1 => 2,   // Depth 1: every 2 days
            2 => 3,   // Depth 2: every 3 days
            3 => 7,   // Depth 3: weekly
            4 => 14,  // Depth 4: bi-weekly
        ];

        $baseDays = $intervals[$url->depth] ?? 30; // Deep URLs: monthly

        // Boost priority URLs (high inbound links)
        $inboundCount = $url->inboundLinks()->count();
        if ($inboundCount > 5) {
            $baseDays = max(1, $baseDays - 1); // Crawl 1 day sooner
        }

        return now()->addDays($baseDays);
    }

    /**
     * Reprioritize all URLs in the database.
     */
    public function reprioritizeAll(): int
    {
        $updated = 0;

        Url::chunk(1000, function ($urls) use (&$updated) {
            foreach ($urls as $url) {
                $priority = $this->calculatePriority($url);
                $nextCrawl = $this->calculateNextCrawl($url);

                $url->update([
                    'priority' => $priority,
                    'next_crawl_at' => $nextCrawl,
                ]);

                $updated++;
            }
        });

        Log::info('Reprioritized URLs', ['count' => $updated]);

        return $updated;
    }

    /**
     * Clean up old queue entries (locked for too long).
     */
    public function cleanupStaleQueue(): int
    {
        $staleThreshold = now()->subHours(1);
        
        $deleted = CrawlQueue::locked()
            ->where('locked_at', '<', $staleThreshold)
            ->delete();

        if ($deleted > 0) {
            Log::warning('Cleaned up stale queue entries', ['count' => $deleted]);
        }

        return $deleted;
    }
}
