<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Link extends Model
{
    protected $fillable = [
        'from_url_id',
        'to_url_id',
        'nofollow',
        'anchor_text',
    ];

    protected $casts = [
        'nofollow' => 'boolean',
    ];

    /**
     * Get the source URL.
     */
    public function fromUrl(): BelongsTo
    {
        return $this->belongsTo(Url::class, 'from_url_id');
    }

    /**
     * Get the target URL.
     */
    public function toUrl(): BelongsTo
    {
        return $this->belongsTo(Url::class, 'to_url_id');
    }

    /**
     * Scope for follow links only.
     */
    public function scopeFollow($query)
    {
        return $query->where('nofollow', false);
    }

    /**
     * Scope for nofollow links only.
     */
    public function scopeNofollow($query)
    {
        return $query->where('nofollow', true);
    }
}
