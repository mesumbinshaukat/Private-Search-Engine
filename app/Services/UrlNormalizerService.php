<?php

namespace App\Services;

use Illuminate\Support\Facades\Log;

class UrlNormalizerService
{
    /**
     * List of tracking parameters to remove from URLs.
     */
    private const TRACKING_PARAMS = [
        'utm_source', 'utm_medium', 'utm_campaign', 'utm_term', 'utm_content',
        'fbclid', 'gclid', 'msclkid', 'mc_cid', 'mc_eid',
        '_ga', '_gl', 'ref', 'referrer', 'source',
    ];

    /**
     * Normalize a URL to a canonical form.
     *
     * @param string $url The URL to normalize
     * @return array{normalized: string, hash: string, host: string, path: string, query_hash: ?string}|null
     */
    public function normalize(string $url): ?array
    {
        try {
            // Parse the URL
            $parsed = parse_url(trim($url));
            
            if (!isset($parsed['scheme']) || !isset($parsed['host'])) {
                return null;
            }

            // 1. Lowercase scheme and host
            $scheme = strtolower($parsed['scheme']);
            $host = strtolower($parsed['host']);

            // 2. Handle Unicode/IDN (convert to punycode)
            if (function_exists('idn_to_ascii')) {
                $host = idn_to_ascii($host, IDNA_DEFAULT, INTL_IDNA_VARIANT_UTS46) ?: $host;
            }

            // 3. Remove default ports
            $port = $parsed['port'] ?? null;
            if (($scheme === 'http' && $port === 80) || ($scheme === 'https' && $port === 443)) {
                $port = null;
            }

            // 4. Normalize path
            $path = $parsed['path'] ?? '/';
            $path = $this->normalizePath($path);

            // 5. Process query parameters
            $query = $parsed['query'] ?? '';
            $normalizedQuery = $this->normalizeQuery($query);
            $queryHash = $normalizedQuery ? hash('sha256', $normalizedQuery) : null;

            // 6. Build normalized URL (without fragment)
            $normalized = $scheme . '://';
            $normalized .= $host;
            if ($port) {
                $normalized .= ':' . $port;
            }
            $normalized .= $path;
            if ($normalizedQuery) {
                $normalized .= '?' . $normalizedQuery;
            }

            // 7. Generate SHA256 hash for uniqueness
            $urlHash = hash('sha256', $normalized);

            return [
                'normalized' => $normalized,
                'hash' => $urlHash,
                'host' => $host,
                'path' => $path,
                'query_hash' => $queryHash,
            ];

        } catch (\Exception $e) {
            Log::warning('URL normalization failed', [
                'url' => $url,
                'error' => $e->getMessage(),
            ]);
            return null;
        }
    }

    /**
     * Normalize a URL path.
     */
    private function normalizePath(string $path): string
    {
        // Remove trailing slash (except for root)
        if ($path !== '/' && str_ends_with($path, '/')) {
            $path = rtrim($path, '/');
        }

        // Resolve relative path segments (., ..)
        $segments = explode('/', $path);
        $stack = [];

        foreach ($segments as $segment) {
            if ($segment === '' || $segment === '.') {
                continue;
            }
            if ($segment === '..') {
                array_pop($stack);
            } else {
                $stack[] = $segment;
            }
        }

        $normalized = '/' . implode('/', $stack);
        
        // Ensure root path is just '/'
        return $normalized === '' ? '/' : $normalized;
    }

    /**
     * Normalize query parameters.
     */
    private function normalizeQuery(string $query): string
    {
        if (empty($query)) {
            return '';
        }

        parse_str($query, $params);

        // Remove tracking parameters
        foreach (self::TRACKING_PARAMS as $trackingParam) {
            unset($params[$trackingParam]);
        }

        if (empty($params)) {
            return '';
        }

        // Sort parameters alphabetically
        ksort($params);

        // Rebuild query string
        return http_build_query($params);
    }

    /**
     * Resolve a relative URL to an absolute URL.
     *
     * @param string $relativeUrl The relative URL
     * @param string $baseUrl The base URL
     * @return string|null The absolute URL or null if resolution fails
     */
    public function makeAbsolute(string $relativeUrl, string $baseUrl): ?string
    {
        $relativeUrl = trim($relativeUrl);
        $baseUrl = trim($baseUrl);

        // Already absolute
        if (parse_url($relativeUrl, PHP_URL_SCHEME)) {
            return $relativeUrl;
        }

        $base = parse_url($baseUrl);
        if (!isset($base['scheme']) || !isset($base['host'])) {
            return null;
        }

        // Protocol-relative URL (//example.com/path)
        if (str_starts_with($relativeUrl, '//')) {
            return $base['scheme'] . ':' . $relativeUrl;
        }

        // Absolute path (/path)
        if (str_starts_with($relativeUrl, '/')) {
            return $base['scheme'] . '://' . $base['host'] . $relativeUrl;
        }

        // Relative path (path or ./path or ../path)
        $basePath = $base['path'] ?? '/';
        $basePath = preg_replace('#/[^/]*$#', '/', $basePath);

        return $base['scheme'] . '://' . $base['host'] . $basePath . $relativeUrl;
    }

    /**
     * Extract the host from a URL.
     */
    public function extractHost(string $url): ?string
    {
        $parsed = parse_url(trim($url));
        return isset($parsed['host']) ? strtolower($parsed['host']) : null;
    }

    /**
     * Check if two URLs are equivalent after normalization.
     */
    public function areEquivalent(string $url1, string $url2): bool
    {
        $normalized1 = $this->normalize($url1);
        $normalized2 = $this->normalize($url2);

        if (!$normalized1 || !$normalized2) {
            return false;
        }

        return $normalized1['hash'] === $normalized2['hash'];
    }
}
