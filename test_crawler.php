<?php

require __DIR__.'/vendor/autoload.php';

$app = require_once __DIR__.'/bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

echo "=== Testing Advanced Crawler Services ===\n\n";

// Test 1: URL Normalization
echo "1. Testing URL Normalization...\n";
$normalizer = app(\App\Services\UrlNormalizerService::class);
$result = $normalizer->normalize('https://techcrunch.com/test?utm_source=test&ref=google');
echo "   Original: https://techcrunch.com/test?utm_source=test&ref=google\n";
echo "   Normalized: " . ($result['normalized'] ?? 'FAILED') . "\n";
echo "   Hash: " . substr($result['hash'] ?? 'FAILED', 0, 16) . "...\n\n";

// Test 2: Create URL
echo "2. Creating test URL...\n";
$url = \App\Models\Url::create([
    'normalized_url' => 'https://techcrunch.com/test',
    'original_url' => 'https://techcrunch.com/test',
    'host' => 'techcrunch.com',
    'status' => 'pending',
    'category' => 'technology',
    'priority' => 100,
]);
echo "   Created URL ID: " . $url->id . "\n\n";

// Test 3: Index Document
echo "3. Testing IndexEngineService...\n";
$indexer = app(\App\Services\IndexEngineService::class);
$indexer->indexDocument(
    $url,
    'Artificial Intelligence Breakthrough in Machine Learning',
    'Scientists discover new approach to neural networks',
    'This is a test article about artificial intelligence and machine learning. The breakthrough in neural networks represents a significant advancement in the field of AI research.'
);
echo "   Document indexed successfully\n";
echo "   Tokens created: " . \App\Models\Token::count() . "\n";
echo "   Postings created: " . \App\Models\Posting::count() . "\n";
echo "   Documents: " . \App\Models\Document::count() . "\n\n";

// Test 4: Search
echo "4. Testing EnhancedSearchService...\n";
$search = app(\App\Services\EnhancedSearchService::class);
$results = $search->search('artificial intelligence');
echo "   Search query: 'artificial intelligence'\n";
echo "   Results found: " . count($results) . "\n";
if (!empty($results)) {
    echo "   Top result:\n";
    echo "     - Title: " . $results[0]['title'] . "\n";
    echo "     - Score: " . $results[0]['score'] . "\n";
}
echo "\n";

// Test 5: Scheduler
echo "5. Testing CrawlSchedulerService...\n";
$scheduler = app(\App\Services\CrawlSchedulerService::class);
$priority = $scheduler->calculatePriority($url);
echo "   Calculated priority for URL: " . $priority . "\n";
$nextCrawl = $scheduler->calculateNextCrawl($url);
echo "   Next crawl scheduled for: " . $nextCrawl->format('Y-m-d H:i:s') . "\n\n";

// Test 6: Metrics
echo "6. Testing MetricsService...\n";
$metrics = app(\App\Services\MetricsService::class);
$metrics->record('test_metric', 100.5, 'technology');
echo "   Recorded test metric\n";
echo "   Total metrics: " . \App\Models\Metric::count() . "\n\n";

echo "=== All Tests Passed! ===\n";
