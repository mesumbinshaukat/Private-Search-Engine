<?php

return [
    'max_concurrent_jobs' => env('CRAWLER_MAX_CONCURRENT_JOBS', 10),
    
    'request_timeout' => env('CRAWLER_REQUEST_TIMEOUT', 10),
    
    'max_page_size' => env('CRAWLER_MAX_PAGE_SIZE', 5242880),
    
    'rate_limit_per_domain' => env('CRAWLER_RATE_LIMIT_PER_DOMAIN', 1),
    
    'user_agent' => env('CRAWLER_USER_AGENT', 'PrivateSearchBot/1.0 (Personal Use; +http://localhost:8000/bot)'),
    
    'respect_robots_txt' => env('CRAWLER_RESPECT_ROBOTS_TXT', true),
    
    'default_crawl_delay' => env('CRAWLER_DEFAULT_CRAWL_DELAY', 1),
    
    'max_redirects' => env('CRAWLER_MAX_REDIRECTS', 5),
    
    'valid_content_types' => [
        'text/html',
        'application/xhtml+xml',
    ],
    
    'retry_attempts' => env('CRAWLER_RETRY_ATTEMPTS', 3),
    
    'retry_delay' => env('CRAWLER_RETRY_DELAY', 5),
    
    'exponential_backoff_multiplier' => env('CRAWLER_BACKOFF_MULTIPLIER', 2),

    'max_crawls_per_category' => env('CRAWLER_MAX_CRAWLS_PER_CATEGORY', 10),

    'allowed_external_domains' => array_filter(explode(',', env('CRAWLER_ALLOWED_EXTERNAL_DOMAINS', ''))),
];
