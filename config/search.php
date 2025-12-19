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
    'fuzziness_threshold' => 2, // Levenshtein distance
    'bm25_k1' => 1.2,
    'bm25_b' => 0.75,
];
