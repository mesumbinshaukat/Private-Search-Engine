<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ParsedRecord extends Model
{
    const VALID_CATEGORIES = ['technology', 'business', 'ai', 'sports', 'politics'];

    protected $fillable = [
        'url',
        'canonical_url',
        'title',
        'description',
        'published_at',
        'category',
        'content_hash',
        'parsed_at',
    ];

    protected $casts = [
        'published_at' => 'datetime',
        'parsed_at' => 'datetime',
    ];

    public function scopeForCategory($query, string $category)
    {
        return $query->where('category', $category);
    }

    public function scopeNewerThan($query, int $days)
    {
        return $query->where('parsed_at', '>=', now()->subDays($days));
    }

    public function scopeOlderThan($query, int $days)
    {
        return $query->where('parsed_at', '<', now()->subDays($days));
    }

    public static function isDuplicate(string $canonicalUrl): bool
    {
        return self::where('canonical_url', $canonicalUrl)->exists();
    }

    public static function isDuplicateByHash(string $contentHash): bool
    {
        return self::where('content_hash', $contentHash)->exists();
    }
}
