<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Metric extends Model
{
    protected $fillable = [
        'metric_type',
        'category',
        'value',
        'metadata',
        'recorded_at',
    ];

    protected $casts = [
        'value' => 'decimal:4',
        'metadata' => 'array',
        'recorded_at' => 'datetime',
    ];

    /**
     * Scope for metrics by type.
     */
    public function scopeByType($query, string $type)
    {
        return $query->where('metric_type', $type);
    }

    /**
     * Scope for metrics by category.
     */
    public function scopeByCategory($query, string $category)
    {
        return $query->where('category', $category);
    }

    /**
     * Scope for metrics within a time range.
     */
    public function scopeInTimeRange($query, $start, $end)
    {
        return $query->whereBetween('recorded_at', [$start, $end]);
    }

    /**
     * Scope for recent metrics.
     */
    public function scopeRecent($query, int $hours = 24)
    {
        return $query->where('recorded_at', '>=', now()->subHours($hours));
    }
}
