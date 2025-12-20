<?php

require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use Illuminate\Support\Facades\Http;
use App\Models\Document;
use App\Models\Url;
use Illuminate\Support\Facades\Storage;

$masterKey = env('API_MASTER_KEY');
$baseUrl = env('APP_URL');

echo "--- 📊 System Record Analysis ---\n\n";

// 1. Check Database Counts
echo "Database Stats:\n";
echo "  - Total Documents (Search Index): " . Document::count() . "\n";
echo "  - Total URLs (Crawler): " . Url::count() . "\n";
echo "  - Crawled URLs: " . Url::where('status', 'crawled')->count() . "\n\n";

// 2. Check JSON Cache Counts
echo "JSON Cache Stats (storage/app/cache):\n";
$categories = config('categories.valid_categories', ['technology', 'business', 'ai', 'sports', 'politics']);
foreach ($categories as $cat) {
    $file = "cache/{$cat}.json";
    if (Storage::exists($file)) {
        $data = json_decode(Storage::get($file), true);
        $count = isset($data['records']) ? count($data['records']) : 0;
        echo "  - {$cat}.json: {$count} records (" . round(Storage::size($file)/1024, 2) . " KB)\n";
        if ($cat === 'technology' && $count > 0) {
            echo "    Keys in first record: " . implode(', ', array_keys($data['records'][0])) . "\n";
            echo "    First record sample: " . json_encode(array_intersect_key($data['records'][0], array_flip(['url', 'indexed_at', 'published_at']))) . "\n";
        }
    } else {
        echo "  - {$cat}.json: NOT FOUND\n";
    }
}
echo "\n";

// 3. Test API Search Endpoint
echo "API Search Test (q=a, category=all, Master Key):\n";
try {
    $request = Illuminate\Http\Request::create('/api/v1/search', 'GET', [
        'q' => 'a', // Common letter to match many records
        'category' => 'all',
        'debug' => true
    ]);
    $request->headers->set('X-API-MASTER-KEY', $masterKey);
    $response = $app->handle($request);
    $data = json_decode($response->getContent(), true);
    
    if (isset($data['status']) && $data['status'] === 'success') {
        echo "  - API Status: SUCCESS\n";
        echo "  - Total Matched: " . ($data['data']['pagination']['total_results'] ?? 'N/A') . "\n";
        echo "  - Total Scanned (Debug): " . ($data['debug']['total_scanned'] ?? 'N/A') . "\n";
        echo "  - Cache Age: " . ($data['meta']['cache_age_seconds'] ?? 'N/A') . "s\n";
    } else {
        echo "  - API Status: " . ($data['status'] ?? 'ERROR') . " (" . ($data['error']['message'] ?? 'no message') . ")\n";
    }
} catch (\Exception $e) {
    echo "  - API Error: " . $e->getMessage() . "\n";
}

echo "\n--- Analysis Finished ---\n";
