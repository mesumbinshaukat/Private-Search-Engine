<?php

namespace App\Jobs;

use App\Models\CrawlJob;
use App\Models\ParsedRecord;
use App\Services\ParserService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Cache;

class ParsePageJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $tries = 3;
    public $timeout = 300;

    private CrawlJob $crawlJob;
    private string $filename;

    public function __construct(CrawlJob $crawlJob, string $filename)
    {
        $this->crawlJob = $crawlJob;
        $this->filename = $filename;
    }

    public function handle(ParserService $parser): void
    {
        Log::info('Starting parse', [
            'url' => $this->crawlJob->url,
            'filename' => $this->filename,
        ]);

        if (!Storage::exists($this->filename)) {
            Log::error('HTML file not found', ['filename' => $this->filename]);
            return;
        }

        $html = Storage::get($this->filename);
        $parsed = $parser->parse($html, $this->crawlJob->url);

        if (!$parsed) {
            Log::warning('Parse failed', ['url' => $this->crawlJob->url]);
            return;
        }

        // Check for existing record by canonical URL (global check)
        $existing = ParsedRecord::where('canonical_url', $parsed['canonical_url'])->first();
        
        if ($existing) {
            $isOld = $existing->parsed_at && $existing->parsed_at->lt(now()->subDays(7));

            if ($isOld) {
                Log::info('Record is older than 7 days, replacing for freshness', ['url' => $this->crawlJob->url, 'id' => $existing->id]);
                $existing->delete();
                $existing = null; // Forces creation below
            } else {
                // Update logic for freshness (content/meta updates)
                $existing->update([
                    'title' => $parsed['title'],
                    'description' => $parsed['description'],
                    'published_at' => $parsed['published_at'],
                    'content_hash' => $parsed['content_hash'],
                    'category' => $this->crawlJob->category, 
                    'parsed_at' => now(),
                ]);
                Log::info('Record updated (canonical match)', ['url' => $this->crawlJob->url, 'id' => $existing->id]);
            }
        }

        if (!$existing) {
            // Also check by raw URL just in case
            $existingByUrl = ParsedRecord::where('url', $parsed['url'])->first();
            
            if ($existingByUrl) {
                $existingByUrl->update([
                    'canonical_url' => $parsed['canonical_url'],
                    'title' => $parsed['title'],
                    'description' => $parsed['description'],
                    'published_at' => $parsed['published_at'],
                    'content_hash' => $parsed['content_hash'],
                    'parsed_at' => now(),
                ]);
                Log::info('Record updated (URL match)', ['url' => $this->crawlJob->url, 'id' => $existingByUrl->id]);
            } else {
                ParsedRecord::create([
                    'url' => $parsed['url'],
                    'canonical_url' => $parsed['canonical_url'],
                    'title' => $parsed['title'],
                    'description' => $parsed['description'],
                    'published_at' => $parsed['published_at'],
                    'category' => $this->crawlJob->category,
                    'content_hash' => $parsed['content_hash'],
                    'parsed_at' => now(),
                ]);
                Log::info('New record created', ['url' => $this->crawlJob->url]);
            }
        }

        $this->discoverLinks($parser, $html);

        Storage::delete($this->filename);
    }

    private function discoverLinks(ParserService $parser, string $html): void
    {
        $maxCrawls = config('crawler.max_crawls_per_category', 10);
        $today = now()->format('Y-m-d');
        $cacheKey = "crawl_count:{$this->crawlJob->category}:{$today}";

        $currentCount = Cache::get($cacheKey, 0);
        if ($currentCount >= $maxCrawls) {
            return;
        }

        $links = $parser->extractLinks($html, $this->crawlJob->url);
        $domain = parse_url($this->crawlJob->url, PHP_URL_HOST);
        $normalizer = app(\App\Services\UrlNormalizer::class);
        $allowedExternal = config('crawler.allowed_external_domains', []);
        
        // Smart filtering keywords based on category
        $keywords = ['tech', 'ai', 'sport', 'business', 'news', 'politics', 'science'];

        foreach ($links as $link) {
            $link = $normalizer->normalize($link);
            
            if ($currentCount >= $maxCrawls) {
                break;
            }

            $linkHost = parse_url($link, PHP_URL_HOST);

            // Cross-domain discovery logic
            if ($linkHost !== $domain) {
                if (!empty($allowedExternal)) {
                    // Only follow if in the allowed list
                    if (!in_array($linkHost, $allowedExternal)) {
                        continue;
                    }
                } else {
                    // Smart fallback: check if host/path contains category-relevant keywords
                    $searchable = strtolower($link);
                    $hasKeyword = false;
                    foreach ($keywords as $kw) {
                        if (str_contains($searchable, $kw)) {
                            $hasKeyword = true;
                            break;
                        }
                    }
                    if (!$hasKeyword) {
                        continue;
                    }
                }
            }

            // Check if URL has failed before (rate limited, etc)
            if (Cache::has("failed_url:" . md5($link))) {
                continue;
            }

            // Check if already crawled or queued in THIS cycle
            if (CrawlJob::where('url', $link)->exists()) {
                continue;
            }

            $newJob = CrawlJob::create([
                'url' => $link,
                'category' => $this->crawlJob->category,
                'status' => CrawlJob::STATUS_PENDING,
            ]);

            CrawlPageJob::dispatch($newJob);
            
            $currentCount = Cache::increment($cacheKey);
        }
    }

    public function failed(\Throwable $exception): void
    {
        Log::error('Parse job failed', [
            'url' => $this->crawlJob->url,
            'error' => $exception->getMessage(),
        ]);
    }
}
