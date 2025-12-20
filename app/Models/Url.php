<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Url extends Model
{
    protected $fillable = [
        'url_hash',
        'normalized_url',
        'original_url',
        'host',
        'path',
        'query_hash',
        'depth',
        'priority',
        'status',
        'last_crawled_at',
        'next_crawl_at',
        'http_status',
        'content_hash',
        'category',
        'failed_reason',
        'retry_count',
    ];

    protected $casts = [
        'last_crawled_at' => 'datetime',
        'next_crawl_at' => 'datetime',
        'depth' => 'integer',
        'priority' => 'integer',
        'http_status' => 'integer',
        'retry_count' => 'integer',
    ];

    /**
     * Get the document associated with this URL.
     */
    public function document(): HasOne
    {
        return $this->hasOne(Document::class);
    }

    /**
     * Get the crawl queue entry for this URL.
     */
    public function crawlQueue(): HasOne
    {
        return $this->hasOne(CrawlQueue::class);
    }

    /**
     * Get outbound links from this URL.
     */
    public function outboundLinks(): HasMany
    {
        return $this->hasMany(Link::class, 'from_url_id');
    }

    /**
     * Get inbound links to this URL.
     */
    public function inboundLinks(): HasMany
    {
        return $this->hasMany(Link::class, 'to_url_id');
    }

    /**
     * Get URLs that this URL links to.
     */
    public function linksTo(): BelongsToMany
    {
        return $this->belongsToMany(Url::class, 'links', 'from_url_id', 'to_url_id')
            ->withPivot('nofollow', 'anchor_text')
            ->withTimestamps();
    }

    /**
     * Get URLs that link to this URL.
     */
    public function linkedFrom(): BelongsToMany
    {
        return $this->belongsToMany(Url::class, 'links', 'to_url_id', 'from_url_id')
            ->withPivot('nofollow', 'anchor_text')
            ->withTimestamps();
    }

    /**
     * Scope for pending URLs.
     */
    public function scopePending($query)
    {
        return $query->where('status', 'pending');
    }

    /**
     * Scope for crawled URLs.
     */
    public function scopeCrawled($query)
    {
        return $query->where('status', 'crawled');
    }

    /**
     * Scope for failed URLs.
     */
    public function scopeFailed($query)
    {
        return $query->where('status', 'failed');
    }

    /**
     * Scope for URLs by category.
     */
    public function scopeForCategory($query, string $category)
    {
        return $query->where('category', $category);
    }

    /**
     * Scope for URLs due for crawling.
     */
    public function scopeDueForCrawl($query)
    {
        return $query->where('next_crawl_at', '<=', now())
            ->orWhereNull('next_crawl_at');
    }
}
