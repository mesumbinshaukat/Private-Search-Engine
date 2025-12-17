<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class RobotsTxtParser
{
    private const CACHE_TTL = 86400;
    private string $userAgent;

    public function __construct()
    {
        $this->userAgent = config('crawler.user_agent', 'PrivateSearchBot/1.0');
    }

    public function isAllowed(string $url): bool
    {
        try {
            $parsedUrl = parse_url($url);
            if (!$parsedUrl || !isset($parsedUrl['scheme'], $parsedUrl['host'])) {
                Log::warning('Invalid URL for robots.txt check', ['url' => $url]);
                return false;
            }

            $robotsTxtUrl = $parsedUrl['scheme'] . '://' . $parsedUrl['host'] . '/robots.txt';
            $robotsTxt = $this->fetchRobotsTxt($robotsTxtUrl);

            if ($robotsTxt === null) {
                return true;
            }

            $path = $parsedUrl['path'] ?? '/';
            return $this->checkPath($robotsTxt, $path);
        } catch (\Exception $e) {
            Log::error('Error checking robots.txt', [
                'url' => $url,
                'error' => $e->getMessage(),
            ]);
            return false;
        }
    }

    public function getCrawlDelay(string $url): int
    {
        try {
            $parsedUrl = parse_url($url);
            if (!$parsedUrl || !isset($parsedUrl['scheme'], $parsedUrl['host'])) {
                return config('crawler.default_crawl_delay', 1);
            }

            $robotsTxtUrl = $parsedUrl['scheme'] . '://' . $parsedUrl['host'] . '/robots.txt';
            $robotsTxt = $this->fetchRobotsTxt($robotsTxtUrl);

            if ($robotsTxt === null) {
                return config('crawler.default_crawl_delay', 1);
            }

            return $this->extractCrawlDelay($robotsTxt);
        } catch (\Exception $e) {
            Log::error('Error getting crawl delay', [
                'url' => $url,
                'error' => $e->getMessage(),
            ]);
            return config('crawler.default_crawl_delay', 1);
        }
    }

    private function fetchRobotsTxt(string $robotsTxtUrl): ?string
    {
        $cacheKey = 'robots_txt:' . md5($robotsTxtUrl);

        return Cache::remember($cacheKey, self::CACHE_TTL, function () use ($robotsTxtUrl) {
            try {
                $response = Http::timeout(5)->get($robotsTxtUrl);

                if ($response->successful()) {
                    return $response->body();
                }

                if ($response->status() === 404) {
                    return null;
                }

                Log::warning('Failed to fetch robots.txt', [
                    'url' => $robotsTxtUrl,
                    'status' => $response->status(),
                ]);

                return null;
            } catch (\Exception $e) {
                Log::warning('Exception fetching robots.txt', [
                    'url' => $robotsTxtUrl,
                    'error' => $e->getMessage(),
                ]);
                return null;
            }
        });
    }

    private function checkPath(string $robotsTxt, string $path): bool
    {
        $lines = explode("\n", $robotsTxt);
        $relevantRules = [];
        $isRelevantUserAgent = false;

        foreach ($lines as $line) {
            $line = trim($line);

            if (empty($line) || str_starts_with($line, '#')) {
                continue;
            }

            if (preg_match('/^User-agent:\s*(.+)$/i', $line, $matches)) {
                $agent = trim($matches[1]);
                $isRelevantUserAgent = ($agent === '*' || stripos($this->userAgent, $agent) !== false);
                continue;
            }

            if ($isRelevantUserAgent && preg_match('/^Disallow:\s*(.+)$/i', $line, $matches)) {
                $disallowedPath = trim($matches[1]);
                $relevantRules[] = ['type' => 'disallow', 'path' => $disallowedPath];
            }

            if ($isRelevantUserAgent && preg_match('/^Allow:\s*(.+)$/i', $line, $matches)) {
                $allowedPath = trim($matches[1]);
                $relevantRules[] = ['type' => 'allow', 'path' => $allowedPath];
            }
        }

        foreach (array_reverse($relevantRules) as $rule) {
            if ($this->pathMatches($path, $rule['path'])) {
                return $rule['type'] === 'allow';
            }
        }

        return true;
    }

    private function pathMatches(string $path, string $pattern): bool
    {
        if ($pattern === '/') {
            return true;
        }

        $pattern = str_replace('*', '.*', preg_quote($pattern, '/'));
        return preg_match('/^' . $pattern . '/i', $path) === 1;
    }

    private function extractCrawlDelay(string $robotsTxt): int
    {
        $lines = explode("\n", $robotsTxt);
        $isRelevantUserAgent = false;

        foreach ($lines as $line) {
            $line = trim($line);

            if (preg_match('/^User-agent:\s*(.+)$/i', $line, $matches)) {
                $agent = trim($matches[1]);
                $isRelevantUserAgent = ($agent === '*' || stripos($this->userAgent, $agent) !== false);
                continue;
            }

            if ($isRelevantUserAgent && preg_match('/^Crawl-delay:\s*(\d+)$/i', $line, $matches)) {
                return (int) $matches[1];
            }
        }

        return config('crawler.default_crawl_delay', 1);
    }
}
