<?php

namespace App\Jobs;

use App\Models\CrawlJob;
use App\Services\CrawlerService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class CrawlPageJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $tries = 3;
    public $timeout = 300;
    public $backoff = [5, 15, 60];

    private CrawlJob $crawlJob;

    public function __construct(CrawlJob $crawlJob)
    {
        $this->crawlJob = $crawlJob;
    }

    public function handle(CrawlerService $crawler): void
    {
        $this->crawlJob->update(['status' => CrawlJob::STATUS_PROCESSING]);

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

            Log::info('Crawl completed', [
                'url' => $this->crawlJob->url,
                'size' => $result['size'],
                'filename' => $result['filename'],
            ]);

            ParsePageJob::dispatch($this->crawlJob, $result['filename']);
        } else {
            $this->crawlJob->update([
                'status' => CrawlJob::STATUS_FAILED,
                'http_status' => $result['http_status'] ?? null,
                'robots_txt_allowed' => $result['robots_txt_allowed'] ?? true,
                'failed_reason' => $result['error'],
            ]);

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

        Log::error('Crawl job failed permanently', [
            'url' => $this->crawlJob->url,
            'error' => $exception->getMessage(),
        ]);
    }
}
