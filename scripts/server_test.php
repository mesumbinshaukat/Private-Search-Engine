
echo "--- Private Search Engine Health Check ---\n";

// 1. Database Stats
echo "\n[1] Database Statistics:\n";
try {
    echo "- Parsed Records: " . \App\Models\ParsedRecord::count() . "\n";
    echo "- Crawl Jobs: " . \App\Models\CrawlJob::count() . " (" . \App\Models\CrawlJob::where('status', \App\Models\CrawlJob::STATUS_COMPLETED)->count() . " completed)\n";
    echo "- Known URLs: " . \App\Models\Url::count() . "\n";
    
    if (\Illuminate\Support\Facades\Schema::hasTable('jobs')) {
        echo "- Queue Size: " . \Illuminate\Support\Facades\DB::table('jobs')->count() . "\n";
    } else {
        echo "- Queue Table: MISSING\n";
    }
} catch (\Exception $e) {
    echo "  ! DB Error: " . $e->getMessage() . "\n";
}

// 2. Google Drive Connectivity
echo "\n[2] Google Drive Status:\n";
try {
    $storage = app(\App\Services\StorageService::class);
    // Try to list a single file to test token
    $files = $storage->listFiles("name contains 'technology' limit 1");
    echo "- Connection: OK\n";
    echo "- Files Found: " . count($files) . "\n";
} catch (\Exception $e) {
    echo "  ! Drive Error: " . $e->getMessage() . "\n";
    if (str_contains($e->getMessage(), 'invalid_grant')) {
        echo "  - TIP: Token has expired or been revoked. Run google-drive:authorize or update token.json\n";
    }
}

// 3. Cache & Locks
echo "\n[3] Cache & Background Processes:\n";
echo "- Refresh Lock (master_refresh_lock): " . (\Illuminate\Support\Facades\Cache::has('master_refresh_lock') ? "LOCKED" : "FREE") . "\n";
echo "- Poor Man's Cron Lock: " . (\Illuminate\Support\Facades\Cache::has('poor-mans-cron') ? "LOCKED" : "FREE") . "\n";

// 4. API Search Test (Internal)
echo "\n[4] Search API Test (Internal):\n";
try {
    $request = \Illuminate\Http\Request::create('/api/v1/search', 'GET', ['q' => 'technology', 'category' => 'all']);
    $response = app()->handle($request);
    echo "- Status Code: " . $response->getStatusCode() . "\n";
    $data = json_decode($response->getContent(), true);
    if ($data && isset($data['data'])) {
        echo "- Results Found: " . count($data['data']['results'] ?? []) . "\n";
    }
} catch (\Exception $e) {
    echo "  ! Search Error: " . $e->getMessage() . "\n";
}

// 6. Config Check
echo "\n[6] Configuration Check:\n";
echo "- Min Token Length: " . config('indexer.min_token_length', 'NOT SET') . "\n";
echo "- Max Crawls Per Category: " . config('crawler.max_crawls_per_category', 'NOT SET') . "\n";

echo "\n--- End of Health Check ---\n";
