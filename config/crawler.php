<?php

return [
    // Basic Settings
    'max_concurrent_jobs' => env('CRAWLER_MAX_CONCURRENT_JOBS', 10),
    'request_timeout' => env('CRAWLER_REQUEST_TIMEOUT', 15),
    'connect_timeout' => env('CRAWLER_CONNECT_TIMEOUT', 5),
    'max_page_size' => env('CRAWLER_MAX_PAGE_SIZE', 5242880),
    'rate_limit_per_domain' => env('CRAWLER_RATE_LIMIT_PER_DOMAIN', 1),
    'user_agent' => env('CRAWLER_USER_AGENT', 'PrivateSearchBot/1.0 (Personal Use; +http://localhost:8000/bot)'),
    
    // Robots.txt Compliance
    'respect_robots_txt' => env('CRAWLER_RESPECT_ROBOTS_TXT', true),
    'default_crawl_delay' => env('CRAWLER_DEFAULT_CRAWL_DELAY', 1),
    
    // Redirects and Retries
    'max_redirects' => env('CRAWLER_MAX_REDIRECTS', 5),
    'retry_attempts' => env('CRAWLER_RETRY_ATTEMPTS', 3),
    'retry_delay' => env('CRAWLER_RETRY_DELAY', 5),
    'exponential_backoff_multiplier' => env('CRAWLER_BACKOFF_MULTIPLIER', 2),
    
    // Content Validation
    'valid_content_types' => [
        'text/html',
        'application/xhtml+xml',
    ],

    // Discovery Settings
    'max_crawls_per_category' => env('CRAWLER_MAX_CRAWLS_PER_CATEGORY', 1000),
    'allowed_external_domains' => array_filter(explode(',', env('CRAWLER_ALLOWED_EXTERNAL_DOMAINS', ''))),
    'max_depth' => env('CRAWLER_MAX_DEPTH', 5),
    
    // Advanced Fetch Engine
    'fetch_workers' => env('CRAWLER_FETCH_WORKERS', 5),
    'fetch_batch_size' => env('CRAWLER_FETCH_BATCH_SIZE', 100),
    
    // User Agent Rotation (for anti-blocking)
    'user_agents' => [
        'Mozilla/5.0 (compatible; PrivateSearchBot/1.0)',
        'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36',
        'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36',
    ],
];
