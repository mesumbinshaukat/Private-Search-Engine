<?php

use App\Models\Url;
use App\Models\CrawlJob;
use App\Services\UrlNormalizerService;
use App\Services\CrawlerService;
use App\Services\ParserService;
use App\Services\IndexEngineService;
use App\Jobs\CrawlPageJob;
use App\Jobs\ParsePageJob;
use Illuminate\Support\Facades\Log;

echo "--- 🔍 Starting System Wiring Verification ---\n\n";

function check(string $label, callable $test) {
    echo "Checking: $label... ";
    try {
        if ($test()) {
            echo "✅ PASS\n";
        } else {
            echo "❌ FAIL\n";
        }
    } catch (\Exception $e) {
        echo "❌ ERROR: " . $e->getMessage() . "\n";
    }
}

// 1. Model Attributes
check("Url model 'url_hash' in fillable", function() {
    $url = new Url();
    return in_array('url_hash', $url->getFillable());
});

check("Url model 'original_url' in fillable", function() {
    $url = new Url();
    return in_array('original_url', $url->getFillable());
});

// 2. Service Signatures & Functionality
check("UrlNormalizerService::normalize functionality", function() {
    $normalizer = app(UrlNormalizerService::class);
    $result = $normalizer->normalize('https://entrepreneur.com');
    return is_array($result) && isset($result['hash']) && !empty($result['hash']);
});

check("UrlNormalizerService handles malformed URLs with null", function() {
    $normalizer = app(UrlNormalizerService::class);
    return $normalizer->normalize('://bad') === null;
});

// 3. Job Handle Signatures (Reflection)
check("CrawlPageJob::handle parameters", function() {
    $ref = new ReflectionMethod(CrawlPageJob::class, 'handle');
    $params = $ref->getParameters();
    
    $types = array_map(fn($p) => $p->getType() ? $p->getType()->getName() : 'none', $params);
    
    $expected = [CrawlerService::class, UrlNormalizerService::class];
    foreach ($expected as $e) {
        if (!in_array($e, $types)) return false;
    }
    return count($params) === 2;
});

check("ParsePageJob::handle parameters", function() {
    $ref = new ReflectionMethod(ParsePageJob::class, 'handle');
    $params = $ref->getParameters();
    
    $types = array_map(fn($p) => $p->getType() ? $p->getType()->getName() : 'none', $params);
    
    $expected = [
        ParserService::class, 
        UrlNormalizerService::class, 
        IndexEngineService::class
    ];
    foreach ($expected as $e) {
        if (!in_array($e, $types)) return false;
    }
    return count($params) === 3;
});

// 4. IndexEngineService Signature
check("IndexEngineService::indexDocument signature", function() {
    $ref = new ReflectionMethod(IndexEngineService::class, 'indexDocument');
    $params = $ref->getParameters();
    
    // indexDocument(Url $url, string $title, ?string $description, ?string $content)
    if (count($params) !== 4) return false;
    if ($params[0]->getType()->getName() !== Url::class) return false;
    
    return true;
});

// 5. Cross-Check Internal Logic (Syntactic check via reflection/source search if needed, but here just basic check)
check("CrawlDailyCommand exists and is wired", function() {
    return class_exists(\App\Console\Commands\CrawlDailyCommand::class);
});

check("MigrateParsedRecordsCommand exists and is wired", function() {
    return class_exists(\App\Console\Commands\MigrateParsedRecordsCommand::class);
});

echo "\n--- 🏁 Verification Finished ---\n";
