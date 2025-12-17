<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CrawlJob extends Model
{
    const STATUS_PENDING = 'pending';
    const STATUS_PROCESSING = 'processing';
    const STATUS_COMPLETED = 'completed';
    const STATUS_FAILED = 'failed';

    protected $fillable = [
        'url',
        'category',
        'status',
        'http_status',
        'robots_txt_allowed',
        'crawled_at',
        'failed_reason',
    ];

    protected $casts = [
        'robots_txt_allowed' => 'boolean',
        'crawled_at' => 'datetime',
    ];

    public function scopePending($query)
    {
        return $query->where('status', self::STATUS_PENDING);
    }

    public function scopeCompleted($query)
    {
        return $query->where('status', self::STATUS_COMPLETED);
    }

    public function scopeFailed($query)
    {
        return $query->where('status', self::STATUS_FAILED);
    }

    public function scopeForCategory($query, string $category)
    {
        return $query->where('category', $category);
    }
}
