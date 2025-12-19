<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Search Synonyms
    |--------------------------------------------------------------------------
    |
    | A simple per-category synonym dictionary.
    |
    */
    'synonyms' => [
        'ai' => [
            'ai' => ['ml', 'machine learning', 'artificial intelligence', 'neural networks'],
            'ml' => ['machine learning', 'ai'],
            'llm' => ['large language model', 'gpt', 'ai'],
        ],
        'technology' => [
            'cpu' => ['processor', 'chip', 'silicon'],
            'gpu' => ['graphics card', 'video card', 'cuda'],
            'ram' => ['memory', 'storage'],
        ],
        'business' => [
            'ceo' => ['chief executive', 'executive', 'leader'],
            'ipo' => ['initial public offering', 'stock market', 'listing'],
        ],
        'sports' => [
            'f1' => ['formula one', 'racing', 'grand prix'],
            'nba' => ['basketball'],
        ],
        'politics' => [
            'un' => ['united nations', 'international'],
            'eu' => ['european union'],
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Search Constants
    |--------------------------------------------------------------------------
    */
    'fuzziness_threshold' => env('SEARCH_FUZZINESS_THRESHOLD', 2), // Levenshtein distance
    'bm25_k1' => env('SEARCH_BM25_K1', 1.2),
    'bm25_b' => env('SEARCH_BM25_B', 0.75),
    
    'default_per_page' => env('SEARCH_DEFAULT_PER_PAGE', 20),
    'slow_threshold_seconds' => env('SEARCH_SLOW_THRESHOLD_SECONDS', 2.0),
    
    'min_highlight_term_length' => env('SEARCH_MIN_HIGHLIGHT_TERM_LENGTH', 2),
    'min_confidence_score' => env('SEARCH_MIN_CONFIDENCE_SCORE', 0.2),

    /*
    |--------------------------------------------------------------------------
    | System Timeouts
    |--------------------------------------------------------------------------
    */
    'master_refresh_timeout' => env('MASTER_REFRESH_TIMEOUT', 3600),
];
