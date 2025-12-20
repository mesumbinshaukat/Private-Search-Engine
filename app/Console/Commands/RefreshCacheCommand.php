<?php

namespace App\Console\Commands;

use App\Models\IndexMetadata;
use App\Services\StorageService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;

class RefreshCacheCommand extends Command
{
    protected $signature = 'cache:refresh {--force : Force refresh even if cache is fresh}';
    protected $description = 'Refresh local cache from Google Drive';

    public function handle(StorageService $storage)
    {
        $this->info('Refreshing cache from Google Drive...');

        $categories = config('categories.valid_categories');

        foreach ($categories as $category) {
            $this->info("Processing category: {$category}");
            
            // 1. List all related files for this category
            $files = $storage->listFiles("name contains '{$category}_' and name contains '.json'");
            
            if (empty($files)) {
                $this->warn("No files found on Drive for category: {$category}");
                continue;
            }

            $allRecords = [];
            // Enforce 30-day age limit for merged historical cache
            $maxAgeDays = config('indexer.max_data_age_days', 30);
            $cutoffDate = now()->subDays($maxAgeDays);

            // Sort files by name DESC to process newer ones first (often timestamped)
            usort($files, fn($a, $b) => strcmp($b->name, $a->name));

            foreach ($files as $file) {
                $this->info("  Downloading {$file->name}...");
                $tempPath = "temp/" . $file->name;
                
                if ($storage->downloadIndex($file->id, $tempPath)) {
                    $content = Storage::get($tempPath);
                    $data = json_decode($content, true);
                    
                    if ($data && isset($data['records'])) {
                        foreach ($data['records'] as $record) {
                            $indexedAt = isset($record['indexed_at']) ? \Illuminate\Support\Carbon::parse($record['indexed_at']) : null;
                            
                            // 2. Filter by Age (30 days)
                            if ($indexedAt && $indexedAt->greaterThanOrEqualTo($cutoffDate)) {
                                $url = $record['url'] ?? null;
                                $hash = $record['url_hash'] ?? $record['content_hash'] ?? null;
                                
                                if (!$url) continue;

                                // 3. Deduplicate by URL or Hash (Keep newest)
                                // Since we sorted files DESC, if we already have this URL/Hash, it's newer (usually)
                                // or we check the specific indexed_at if available
                                $existing = $allRecords[$url] ?? null;
                                
                                if (!$existing || ($indexedAt && \Illuminate\Support\Carbon::parse($existing['indexed_at'])->lt($indexedAt))) {
                                    $allRecords[$url] = $record;
                                }
                            }
                        }
                    }
                    Storage::delete($tempPath);
                } else {
                    $this->error("  ✗ Failed to download {$file->name}");
                }
            }

            if (!empty($allRecords)) {
                // 4. Final Sorting by indexed_at DESC
                $records = array_values($allRecords);
                usort($records, function($a, $b) {
                    $dateA = isset($a['indexed_at']) ? strtotime($a['indexed_at']) : 0;
                    $dateB = isset($b['indexed_at']) ? strtotime($b['indexed_at']) : 0;
                    return $dateB <=> $dateA;
                });

                $cacheFile = "cache/{$category}.json";
                $mergedData = [
                    'meta' => [
                        'category' => $category,
                        'generated_at' => now()->toIso8601String(),
                        'record_count' => count($records),
                        'schema_version' => config('indexer.schema_version', '1.0'),
                    ],
                    'records' => $records,
                ];

                Storage::put($cacheFile, json_encode($mergedData, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
                $this->info("✓ {$category} cache updated with " . count($records) . " records");
            }
        }

        $this->info('Cache refresh complete');

        return Command::SUCCESS;
    }
}
