<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Document extends Model
{
    protected $fillable = [
        'url_id',
        'title',
        'description',
        'language',
        'content',
        'content_hash',
        'word_count',
        'metadata',
        'published_at',
        'indexed_at',
    ];

    protected $casts = [
        'metadata' => 'array',
        'published_at' => 'datetime',
        'indexed_at' => 'datetime',
        'word_count' => 'integer',
    ];

    /**
     * Get the URL for this document.
     */
    public function url(): BelongsTo
    {
        return $this->belongsTo(Url::class);
    }

    /**
     * Get the postings for this document.
     */
    public function postings(): HasMany
    {
        return $this->hasMany(Posting::class, 'url_id', 'url_id');
    }

    /**
     * Scope for documents by language.
     */
    public function scopeByLanguage($query, string $language)
    {
        return $query->where('language', $language);
    }

    /**
     * Scope for recently indexed documents.
     */
    public function scopeRecentlyIndexed($query, int $days = 7)
    {
        return $query->where('indexed_at', '>=', now()->subDays($days));
    }

    /**
     * Get the document length (for BM25 scoring).
     */
    public function getLength(): int
    {
        return $this->word_count;
    }
}
