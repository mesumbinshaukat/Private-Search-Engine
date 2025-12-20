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
        $baseInterval = 7; // days

        // Adjust based on depth
        $depthMultiplier = 1 + ($url->depth * 0.5);
        
        // Adjust based on change frequency (if we have history)
        // For now, use base interval
        $interval = $baseInterval * $depthMultiplier;

        return now()->addDays((int) $interval);
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
