<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class RateLimiter
{
    private const CACHE_PREFIX = 'rate_limit:';

    public function shouldWait(string $domain): bool
    {
        $cacheKey = self::CACHE_PREFIX . $domain;
        return Cache::has($cacheKey);
    }

    public function getWaitTime(string $domain): int
    {
        $cacheKey = self::CACHE_PREFIX . $domain;
        $lastRequestTime = Cache::get($cacheKey);

        if ($lastRequestTime === null) {
            return 0;
        }

        $rateLimitSeconds = config('crawler.rate_limit_per_domain', 1);
        $elapsedTime = time() - $lastRequestTime;
        $waitTime = max(0, $rateLimitSeconds - $elapsedTime);

        return $waitTime;
    }

    public function wait(string $domain): void
    {
        $waitTime = $this->getWaitTime($domain);

        if ($waitTime > 0) {
            Log::debug('Rate limiting: waiting for domain', [
                'domain' => $domain,
                'wait_seconds' => $waitTime,
            ]);
            sleep($waitTime);
        }
    }

    public function recordRequest(string $domain): void
    {
        $cacheKey = self::CACHE_PREFIX . $domain;
        $ttl = config('crawler.rate_limit_per_domain', 1) * 2;
        
        Cache::put($cacheKey, time(), $ttl);
    }

    public function getDomain(string $url): ?string
    {
        $parsedUrl = parse_url($url);
        
        if (!$parsedUrl || !isset($parsedUrl['host'])) {
            return null;
        }

        return $parsedUrl['host'];
    }
}
