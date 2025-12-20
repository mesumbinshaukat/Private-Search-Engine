<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Token extends Model
{
    protected $fillable = [
        'token',
        'document_frequency',
    ];

    protected $casts = [
        'document_frequency' => 'integer',
    ];

    /**
     * Get the postings for this token.
     */
    public function postings(): HasMany
    {
        return $this->hasMany(Posting::class);
    }

    /**
     * Calculate IDF (Inverse Document Frequency).
     */
    public function calculateIdf(int $totalDocuments): float
    {
        if ($this->document_frequency === 0) {
            return 0.0;
        }

        return log($totalDocuments / $this->document_frequency);
    }

    /**
     * Scope for tokens with minimum document frequency.
     */
    public function scopeMinDocFreq($query, int $minFreq)
    {
        return $query->where('document_frequency', '>=', $minFreq);
    }

    /**
     * Scope for tokens with maximum document frequency.
     */
    public function scopeMaxDocFreq($query, int $maxFreq)
    {
        return $query->where('document_frequency', '<=', $maxFreq);
    }
}
