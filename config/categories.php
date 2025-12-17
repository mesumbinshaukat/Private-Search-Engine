<?php

return [
    'categories' => [
        'technology' => [
            'id' => 'technology',
            'name' => 'Technology',
            'description' => 'Software, hardware, programming, tech industry news',
            'seed_urls' => [
                'https://techcrunch.com',
                'https://arstechnica.com',
                'https://theverge.com',
                'https://wired.com',
                'https://zdnet.com',
            ],
        ],
        'business' => [
            'id' => 'business',
            'name' => 'Business',
            'description' => 'Finance, markets, entrepreneurship, corporate news',
            'seed_urls' => [
                'https://bloomberg.com',
                'https://reuters.com/business',
                'https://wsj.com',
                'https://ft.com',
                'https://forbes.com',
            ],
        ],
        'ai' => [
            'id' => 'ai',
            'name' => 'AI',
            'description' => 'Artificial intelligence, machine learning, AI research and applications',
            'seed_urls' => [
                'https://venturebeat.com/ai',
                'https://technologyreview.com/topic/artificial-intelligence',
                'https://artificialintelligence-news.com',
                'https://aitrends.com',
                'https://syncedreview.com',
            ],
        ],
        'sports' => [
            'id' => 'sports',
            'name' => 'Sports',
            'description' => 'All sports news, events, and analysis',
            'seed_urls' => [
                'https://espn.com',
                'https://bbc.com/sport',
                'https://theguardian.com/sport',
                'https://si.com',
                'https://cbssports.com',
            ],
        ],
        'politics' => [
            'id' => 'politics',
            'name' => 'Politics',
            'description' => 'Political news, policy, elections, government',
            'seed_urls' => [
                'https://politico.com',
                'https://thehill.com',
                'https://reuters.com/politics',
                'https://bbc.com/news/politics',
                'https://apnews.com/politics',
            ],
        ],
    ],

    'valid_categories' => ['technology', 'business', 'ai', 'sports', 'politics'],
];
