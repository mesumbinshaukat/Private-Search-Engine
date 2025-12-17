<?php

namespace App\Services;

use App\Models\CrawlJob;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class CrawlerService
{
    private RobotsTxtParser $robotsTxtParser;
    private RateLimiter $rateLimiter;

    public function __construct(RobotsTxtParser $robotsTxtParser, RateLimiter $rateLimiter)
    {
        $this->robotsTxtParser = $robotsTxtParser;
        $this->rateLimiter = $rateLimiter;
    }

    public function crawl(string $url, string $category): array
    {
        $domain = $this->rateLimiter->getDomain($url);
        
        if (!$domain) {
            return [
                'success' => false,
                'error' => 'Invalid URL: could not extract domain',
            ];
        }

        if (config('crawler.respect_robots_txt', true) && !$this->robotsTxtParser->isAllowed($url)) {
            return [
                'success' => false,
                'error' => 'Disallowed by robots.txt',
                'robots_txt_allowed' => false,
            ];
        }

        $this->rateLimiter->wait($domain);

        try {
            $response = Http::timeout(config('crawler.request_timeout', 10))
                ->withHeaders([
                    'User-Agent' => config('crawler.user_agent'),
                ])
                ->get($url);

            $this->rateLimiter->recordRequest($domain);

            if ($response->status() === 429) {
                return [
                    'success' => false,
                    'error' => 'Rate limited by server (429)',
                    'http_status' => 429,
                    'should_backoff' => true,
                ];
            }

            if ($response->status() >= 500) {
                return [
                    'success' => false,
                    'error' => 'Server error (' . $response->status() . ')',
                    'http_status' => $response->status(),
                    'should_retry' => true,
                ];
            }

            if (!$response->successful()) {
                return [
                    'success' => false,
                    'error' => 'HTTP error (' . $response->status() . ')',
                    'http_status' => $response->status(),
                ];
            }

            $contentType = $response->header('Content-Type');
            if (!$this->isValidContentType($contentType)) {
                return [
                    'success' => false,
                    'error' => 'Invalid content type: ' . $contentType,
                    'http_status' => $response->status(),
                ];
            }

            $body = $response->body();
            $bodySize = strlen($body);

            if ($bodySize > config('crawler.max_page_size', 5242880)) {
                return [
                    'success' => false,
                    'error' => 'Page too large: ' . $bodySize . ' bytes',
                    'http_status' => $response->status(),
                ];
            }

            $filename = $this->saveHtml($url, $body, $category);

            return [
                'success' => true,
                'http_status' => $response->status(),
                'content_type' => $contentType,
                'size' => $bodySize,
                'filename' => $filename,
            ];

        } catch (\Illuminate\Http\Client\ConnectionException $e) {
            return [
                'success' => false,
                'error' => 'Connection timeout or network error',
                'exception' => $e->getMessage(),
                'should_retry' => true,
            ];
        } catch (\Exception $e) {
            Log::error('Crawler exception', [
                'url' => $url,
                'error' => $e->getMessage(),
            ]);

            return [
                'success' => false,
                'error' => 'Unexpected error: ' . $e->getMessage(),
            ];
        }
    }

    private function isValidContentType(?string $contentType): bool
    {
        if (!$contentType) {
            return false;
        }

        $validTypes = config('crawler.valid_content_types', ['text/html', 'application/xhtml+xml']);

        foreach ($validTypes as $validType) {
            if (str_contains(strtolower($contentType), strtolower($validType))) {
                return true;
            }
        }

        return false;
    }

    private function saveHtml(string $url, string $html, string $category): string
    {
        $hash = md5($url . time());
        $filename = "crawl/{$category}/{$hash}.html";
        
        Storage::put($filename, $html);
        
        return $filename;
    }
}
