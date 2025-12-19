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
            
            // List all files on Drive for this category
            $files = $storage->listFiles("name contains '{$category}_' and name contains '.json'");
            
            if (empty($files)) {
                $this->warn("No files found on Drive for category: {$category}");
                continue;
            }

            $allRecords = [];
            $maxAgeDays = config('indexer.max_data_age_days', 5);
            $cutoffDate = now()->subDays($maxAgeDays);

            foreach ($files as $file) {
                $this->info("  Downloading {$file->name}...");
                $tempPath = "temp/" . $file->name;
                
                if ($storage->downloadIndex($file->id, $tempPath)) {
                    $content = Storage::get($tempPath);
                    $data = json_decode($content, true);
                    
                    if ($data && isset($data['records'])) {
                        foreach ($data['records'] as $record) {
                            $indexedAt = isset($record['indexed_at']) ? \Illuminate\Support\Carbon::parse($record['indexed_at']) : null;
                            if ($indexedAt && $indexedAt->greaterThanOrEqualTo($cutoffDate)) {
                                $url = $record['url'];
                                $allRecords[$url] = $record; // Use URL as key for deduplication
                            }
                        }
                    }
                    Storage::delete($tempPath);
                } else {
                    $this->error("  ✗ Failed to download {$file->name}");
                }
            }

            if (!empty($allRecords)) {
                $cacheFile = "cache/{$category}.json";
                $mergedData = [
                    'meta' => [
                        'category' => $category,
                        'generated_at' => now()->toIso8601String(),
                        'record_count' => count($allRecords),
                        'schema_version' => config('indexer.schema_version', '1.0'),
                    ],
                    'records' => array_values($allRecords),
                ];

                Storage::put($cacheFile, json_encode($mergedData, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
                $this->info("✓ {$category} cache updated with " . count($allRecords) . " records");
            }
        }

        $this->info('Cache refresh complete');

        return Command::SUCCESS;
    }
}
