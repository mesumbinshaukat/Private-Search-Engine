<?php

namespace App\Services;

use App\Models\Host;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class RobotsTxtService
{
    /**
     * Check if a URL is allowed to be crawled according to robots.txt.
     *
     * @param string $url The URL to check
     * @param string $userAgent The user agent to check for (default: *)
     * @return bool True if allowed, false if disallowed
     */
    public function isAllowed(string $url, string $userAgent = '*'): bool
    {
        $host = parse_url($url, PHP_URL_HOST);
        if (!$host) {
            return false;
        }

        $hostRecord = $this->getOrFetchHost($host);
        
        if (!$hostRecord || !$hostRecord->robots_txt_exists) {
            // No robots.txt means everything is allowed
            return true;
        }

        $path = parse_url($url, PHP_URL_PATH) ?: '/';
        $query = parse_url($url, PHP_URL_QUERY);
        if ($query) {
            $path .= '?' . $query;
        }

        // Check disallow rules first
        if ($this->matchesRules($path, $hostRecord->disallow_rules, $userAgent)) {
            // Check if there's a more specific allow rule
            if ($this->matchesRules($path, $hostRecord->allow_rules, $userAgent)) {
                return true;
            }
            return false;
        }

        return true;
    }

    /**
     * Get the crawl delay for a host.
     *
     * @param string $host The host to check
     * @param string $userAgent The user agent
     * @return float|null The crawl delay in seconds, or null if not specified
     */
    public function getCrawlDelay(string $host, string $userAgent = '*'): ?float
    {
        $hostRecord = $this->getOrFetchHost($host);
        
        if (!$hostRecord) {
            return null;
        }

        return $hostRecord->getCrawlDelay($userAgent);
    }

    /**
     * Get or fetch host record with robots.txt data.
     */
    private function getOrFetchHost(string $host): ?Host
    {
        $hostRecord = Host::where('host', $host)->first();

        // Fetch if doesn't exist or cache is expired
        if (!$hostRecord || $hostRecord->isCacheExpired()) {
            $this->fetchAndCacheRobotsTxt($host);
            $hostRecord = Host::where('host', $host)->first();
        }

        return $hostRecord;
    }

    /**
     * Fetch and cache robots.txt for a host.
     */
    private function fetchAndCacheRobotsTxt(string $host): void
    {
        $robotsUrl = "https://{$host}/robots.txt";

        try {
            $response = Http::timeout(10)
                ->withoutVerifying()
                ->get($robotsUrl);

            if ($response->successful()) {
                $robotsTxt = $response->body();
                $parsed = $this->parseRobotsTxt($robotsTxt);

                Host::updateOrCreate(
                    ['host' => $host],
                    [
                        'robots_fetched_at' => now(),
                        'robots_txt_raw' => $robotsTxt,
                        'robots_txt_exists' => true,
                        'crawl_delay' => $parsed['crawl_delay'],
                        'allow_rules' => $parsed['allow'],
                        'disallow_rules' => $parsed['disallow'],
                    ]
                );

                Log::info('Robots.txt fetched and cached', ['host' => $host]);
            } else {
                // No robots.txt or error - allow everything
                Host::updateOrCreate(
                    ['host' => $host],
                    [
                        'robots_fetched_at' => now(),
                        'robots_txt_exists' => false,
                    ]
                );

                Log::info('No robots.txt found, allowing all', ['host' => $host]);
            }
        } catch (\Exception $e) {
            // On error, assume no robots.txt
            Host::updateOrCreate(
                ['host' => $host],
                [
                    'robots_fetched_at' => now(),
                    'robots_txt_exists' => false,
                ]
            );

            Log::warning('Failed to fetch robots.txt', [
                'host' => $host,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Parse robots.txt content.
     *
     * @return array{crawl_delay: array, allow: array, disallow: array}
     */
    private function parseRobotsTxt(string $content): array
    {
        $lines = explode("\n", $content);
        $currentUserAgent = null;
        $crawlDelay = [];
        $allow = [];
        $disallow = [];

        foreach ($lines as $line) {
            $line = trim($line);
            
            // Skip comments and empty lines
            if (empty($line) || str_starts_with($line, '#')) {
                continue;
            }

            // Split on first colon
            $parts = explode(':', $line, 2);
            if (count($parts) !== 2) {
                continue;
            }

            $directive = strtolower(trim($parts[0]));
            $value = trim($parts[1]);

            switch ($directive) {
                case 'user-agent':
                    $currentUserAgent = strtolower($value);
                    break;

                case 'disallow':
                    if ($currentUserAgent && !empty($value)) {
                        $disallow[] = [
                            'user_agent' => $currentUserAgent,
                            'pattern' => $value,
                        ];
                    }
                    break;

                case 'allow':
                    if ($currentUserAgent && !empty($value)) {
                        $allow[] = [
                            'user_agent' => $currentUserAgent,
                            'pattern' => $value,
                        ];
                    }
                    break;

                case 'crawl-delay':
                    if ($currentUserAgent && is_numeric($value)) {
                        $crawlDelay[$currentUserAgent] = (float) $value;
                    }
                    break;
            }
        }

        return [
            'crawl_delay' => $crawlDelay,
            'allow' => $allow,
            'disallow' => $disallow,
        ];
    }

    /**
     * Check if a path matches any of the rules for a user agent.
     */
    private function matchesRules(string $path, ?array $rules, string $userAgent): bool
    {
        if (!$rules) {
            return false;
        }

        $userAgent = strtolower($userAgent);
        $matchingRules = [];

        // Collect rules for this user agent and wildcard
        foreach ($rules as $rule) {
            $ruleAgent = strtolower($rule['user_agent'] ?? '*');
            if ($ruleAgent === $userAgent || $ruleAgent === '*') {
                $matchingRules[] = $rule['pattern'];
            }
        }

        // Check if path matches any pattern
        foreach ($matchingRules as $pattern) {
            if ($this->matchesPattern($path, $pattern)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Check if a path matches a robots.txt pattern.
     * Supports wildcards: * (any characters) and $ (end of URL)
     */
    private function matchesPattern(string $path, string $pattern): bool
    {
        // Handle end-of-URL marker
        $endMatch = str_ends_with($pattern, '$');
        if ($endMatch) {
            $pattern = rtrim($pattern, '$');
        }

        // Convert robots.txt pattern to regex
        $regex = preg_quote($pattern, '/');
        $regex = str_replace('\*', '.*', $regex);
        
        if ($endMatch) {
            $regex = '/^' . $regex . '$/';
        } else {
            $regex = '/^' . $regex . '/';
        }

        return (bool) preg_match($regex, $path);
    }
}
