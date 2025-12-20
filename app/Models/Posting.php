<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Posting extends Model
{
    protected $fillable = [
        'token_id',
        'url_id',
        'term_frequency',
        'positions',
    ];

    protected $casts = [
        'term_frequency' => 'integer',
        'positions' => 'array',
    ];

    /**
     * Get the token for this posting.
     */
    public function token(): BelongsTo
    {
        return $this->belongsTo(Token::class);
    }

    /**
     * Get the URL for this posting.
     */
    public function url(): BelongsTo
    {
        return $this->belongsTo(Url::class);
    }

    /**
     * Calculate TF-IDF score for this posting.
     */
    public function calculateTfIdf(float $idf): float
    {
        return $this->term_frequency * $idf;
    }

    /**
     * Calculate BM25 score for this posting.
     * 
     * @param float $idf Inverse document frequency
     * @param int $docLength Document length in words
     * @param float $avgDocLength Average document length
     * @param float $k1 BM25 k1 parameter (default 1.2)
     * @param float $b BM25 b parameter (default 0.75)
     */
    public function calculateBm25(
        float $idf,
        int $docLength,
        float $avgDocLength,
        float $k1 = 1.2,
        float $b = 0.75
    ): float {
        $tf = $this->term_frequency;
        $numerator = $tf * ($k1 + 1);
        $denominator = $tf + $k1 * (1 - $b + $b * ($docLength / $avgDocLength));
        
        return $idf * ($numerator / $denominator);
    }
}
