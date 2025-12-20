<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Host extends Model
{
    protected $fillable = [
        'host',
        'robots_fetched_at',
        'crawl_delay',
        'allow_rules',
        'disallow_rules',
        'robots_txt_raw',
        'robots_txt_exists',
    ];

    protected $casts = [
        'robots_fetched_at' => 'datetime',
        'crawl_delay' => 'array',
        'allow_rules' => 'array',
        'disallow_rules' => 'array',
        'robots_txt_exists' => 'boolean',
    ];

    /**
     * Check if robots.txt cache is expired (24 hours).
     */
    public function isCacheExpired(): bool
    {
        if (!$this->robots_fetched_at) {
            return true;
        }

        return $this->robots_fetched_at->diffInHours(now()) >= 24;
    }

    /**
     * Get crawl delay for a specific user agent.
     */
    public function getCrawlDelay(string $userAgent = '*'): ?float
    {
        if (!$this->crawl_delay) {
            return null;
        }

        // Try exact match first
        if (isset($this->crawl_delay[$userAgent])) {
            return (float) $this->crawl_delay[$userAgent];
        }

        // Fall back to wildcard
        if (isset($this->crawl_delay['*'])) {
            return (float) $this->crawl_delay['*'];
        }

        return null;
    }
}
