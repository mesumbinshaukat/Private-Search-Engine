<?php

namespace App\Jobs;

use App\Models\CrawlJob;
use App\Models\Url;
use App\Services\CrawlerService;
use App\Services\UrlNormalizerService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class CrawlPageJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $tries = 5;
    public $timeout = 600;
    public $backoff = [10, 30, 120, 600];

    private CrawlJob $crawlJob;

    public function __construct(CrawlJob $crawlJob)
    {
        $this->crawlJob = $crawlJob;
    }

        public function handle(CrawlerService $crawler, UrlNormalizerService $normalizer): void
    {
        $this->crawlJob->update(['status' => CrawlJob::STATUS_PROCESSING]);

        // Track URL in urls table
        $normalized = $normalizer->normalize($this->crawlJob->url);
        if ($normalized) {
            $urlRecord = Url::firstOrCreate(
                ['url_hash' => $normalized['hash']],
                [
                    'original_url' => $this->crawlJob->url,
                    'normalized_url' => $normalized['normalized'],
                    'host' => $normalized['host'],
                    'path' => $normalized['path'],
                    'category' => $this->crawlJob->category,
                    'depth' => 0,
                    'priority' => 50,
                    'status' => 'pending',
                ]
            );
            
            // Update status to indicate crawling started
            $urlRecord->update(['status' => 'pending']);
        }

        Log::info('Starting crawl', [
            'url' => $this->crawlJob->url,
            'category' => $this->crawlJob->category,
        ]);

        $result = $crawler->crawl($this->crawlJob->url, $this->crawlJob->category);

        if ($result['success']) {
            $this->crawlJob->update([
                'status' => CrawlJob::STATUS_COMPLETED,
                'http_status' => $result['http_status'],
                'crawled_at' => now(),
            ]);
            
            // Update URL record status
            if (isset($urlRecord)) {
                $urlRecord->update([
                    'status' => 'crawled',
                    'http_status' => $result['http_status'],
                    'last_crawled_at' => now(),
                ]);
            }

            Log::info('Crawl completed', [
                'url' => $this->crawlJob->url,
                'size' => $result['size'],
                'filename' => $result['filename'],
            ]);

            ParsePageJob::dispatch($this->crawlJob, $result['filename']);
        } else {
            // Don't retry rate limit errors (429) - mark as failed immediately
            if (isset($result['http_status']) && $result['http_status'] === 429) {
                $this->crawlJob->update([
                    'status' => CrawlJob::STATUS_FAILED,
                    'http_status' => 429,
                    'failed_reason' => 'Rate limited by server (429)',
                ]);
                
                // Update URL record
                if (isset($urlRecord)) {
                    $urlRecord->update([
                        'status' => 'failed',
                        'http_status' => 429,
                        'failed_reason' => 'Rate limited by server',
                    ]);
                }
                
                // Cache this URL as rate-limited to prevent re-queuing
                \Illuminate\Support\Facades\Cache::put("failed_url:" . md5($this->crawlJob->url), true, now()->addDay());
                
                Log::warning('URL rate limited, marked as failed and cached', [
                    'url' => $this->crawlJob->url,
                ]);
                
                return; // Don't retry
            }

            $this->crawlJob->update([
                'status' => CrawlJob::STATUS_FAILED,
                'http_status' => $result['http_status'] ?? null,
                'robots_txt_allowed' => $result['robots_txt_allowed'] ?? true,
                'failed_reason' => $result['error'],
            ]);
            
            // Update URL record
            if (isset($urlRecord)) {
                $urlRecord->update([
                    'status' => 'failed',
                    'http_status' => $result['http_status'] ?? null,
                    'failed_reason' => $result['error'],
                ]);
            }

            Log::warning('Crawl failed', [
                'url' => $this->crawlJob->url,
                'error' => $result['error'],
            ]);

            if ($result['should_retry'] ?? false) {
                $this->release($this->backoff[$this->attempts() - 1] ?? 60);
            }

            if ($result['should_backoff'] ?? false) {
                $this->release(300);
            }
        }
    }

    public function failed(\Throwable $exception): void
    {
        $this->crawlJob->update([
            'status' => CrawlJob::STATUS_FAILED,
            'failed_reason' => 'Job failed after retries: ' . $exception->getMessage(),
        ]);

        // Cache this URL as permanently failed
        \Illuminate\Support\Facades\Cache::put("failed_url:" . md5($this->crawlJob->url), true, now()->addDay());

        Log::error('Crawl job failed permanently', [
            'url' => $this->crawlJob->url,
            'error' => $exception->getMessage(),
        ]);
    }
}
