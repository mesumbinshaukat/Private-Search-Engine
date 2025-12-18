<?php

return [
    'categories' => [
        'technology' => [
            'id' => 'technology',
            'name' => 'Technology',
            'description' => 'Software, hardware, programming, tech industry news',
            'seed_urls' => [
                'https://cnet.com/news',
                'https://techspot.com',
                'https://theverge.com',
                'https://wired.com/category/science',
                'https://zdnet.com',
            ],
        ],
        'business' => [
            'id' => 'business',
            'name' => 'Business',
            'description' => 'Finance, markets, entrepreneurship, corporate news',
            'seed_urls' => [
                'https://businessinsider.com',
                'https://cnbc.com/id/10001147',
                'https://economist.com',
                'https://forbes.com/business',
                'https://entrepreneur.com',
            ],
        ],
        'ai' => [
            'id' => 'ai',
            'name' => 'AI',
            'description' => 'Artificial intelligence, machine learning, AI research and applications',
            'seed_urls' => [
                'https://venturebeat.com/ai',
                'https://technologyreview.com/topic/artificial-intelligence',
                'https://aitrends.com',
                'https://syncedreview.com',
                'https://towardsdatascience.com',
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
                'https://nbcsports.com',
            ],
        ],
        'politics' => [
            'id' => 'politics',
            'name' => 'Politics',
            'description' => 'Political news, policy, elections, government',
            'seed_urls' => [
                'https://politico.com',
                'https://thehill.com',
                'https://bbc.com/news/politics',
                'https://apnews.com/politics',
                'https://vox.com/politics',
            ],
        ],
    ],

    'valid_categories' => ['technology', 'business', 'ai', 'sports', 'politics'],
];
