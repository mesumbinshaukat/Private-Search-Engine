<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CrawlQueue extends Model
{
    protected $table = 'crawl_queue';

    protected $fillable = [
        'url_id',
        'scheduled_at',
        'locked_at',
        'worker_id',
    ];

    protected $casts = [
        'scheduled_at' => 'datetime',
        'locked_at' => 'datetime',
    ];

    /**
     * Get the URL for this queue entry.
     */
    public function url(): BelongsTo
    {
        return $this->belongsTo(Url::class);
    }

    /**
     * Scope for unlocked queue entries.
     */
    public function scopeUnlocked($query)
    {
        return $query->whereNull('locked_at');
    }

    /**
     * Scope for locked queue entries.
     */
    public function scopeLocked($query)
    {
        return $query->whereNotNull('locked_at');
    }

    /**
     * Scope for due queue entries.
     */
    public function scopeDue($query)
    {
        return $query->where('scheduled_at', '<=', now());
    }

    /**
     * Lock this queue entry for a worker.
     */
    public function lock(string $workerId): bool
    {
        return $this->update([
            'locked_at' => now(),
            'worker_id' => $workerId,
        ]);
    }

    /**
     * Unlock this queue entry.
     */
    public function unlock(): bool
    {
        return $this->update([
            'locked_at' => null,
            'worker_id' => null,
        ]);
    }
}
