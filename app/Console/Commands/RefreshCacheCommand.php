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

            // Sort files by name DESC to process newest first (heuristic)
            usort($files, fn($a, $b) => strcmp($b->name, $a->name));

            $allRecords = [];
            $maxAgeDays = config('indexer.max_data_age_days', 30);
            $cutoffDate = now()->subDays($maxAgeDays);

            foreach ($files as $file) {
                try {
                    $tempPath = "temp/cache_refresh_{$file->id}.json";
                    if ($storage->downloadIndex($file->id, $tempPath)) {
                        $content = Storage::get($tempPath);
                        $data = json_decode($content, true);
                        
                        if ($data && isset($data['records'])) {
                            $fileCount = count($data['records']);
                            $keptCount = 0;
                            
                            foreach ($data['records'] as $record) {
                                // Enforce age limit (default 30 days)
                                $indexedAtStr = $record['indexed_at'] ?? $record['published_at'] ?? null;
                                $indexedAt = $indexedAtStr ? \Illuminate\Support\Carbon::parse($indexedAtStr) : null;
                                
                                // If indexedAt is null, treat it as older than cutoffDate (i.e., don't keep it unless it's explicitly newer)
                                if (!$indexedAt || $indexedAt->greaterThanOrEqualTo($cutoffDate)) {
                                    $url = $record['url'] ?? null;
                                    if (!$url) {
                                        // Skip records without a URL, as it's essential for deduplication and access
                                        continue;
                                    }
                                    $hash = $record['content_hash'] ?? md5($url . ($record['description'] ?? ''));
                                    
                                    // Deduplicate: Keep if not seen, or if this one is newer
                                    // We use URL as the primary key for deduplication
                                    $existing = $allRecords[$url] ?? null;
                                    
                                    if (!$existing || ($indexedAt && isset($existing['indexed_at']) && $indexedAt->gt(\Illuminate\Support\Carbon::parse($existing['indexed_at'])))) {
                                        $allRecords[$url] = $record;
                                        $keptCount++;
                                    }
                                }
                            }
                            $this->info("  - Processed {$file->name}: {$keptCount}/{$fileCount} records kept.");
                        } else {
                            $this->warn("  - Processed {$file->name}: No records found or invalid JSON.");
                        }
                        Storage::delete($tempPath);
                    } else {
                        $this->error("  - Failed to download {$file->name}");
                    }
                } catch (\Exception $e) {
                    $this->error("  - Error processing {$file->name}: " . $e->getMessage());
                    // Ensure temp file is cleaned up even on error
                    if (Storage::exists($tempPath)) {
                        Storage::delete($tempPath);
                    }
                }
            }

            // The $allRecords array already contains unique records by URL due to the deduplication logic inside the loop.
            // We just need to convert it to a simple array for final processing.
            $finalRecords = array_values($allRecords);

            // Sort finally by indexed_at DESC
            usort($finalRecords, function($a, $b) {
                // Fallback to 0 if indexed_at is missing, effectively pushing them to the end
                $dateA = isset($a['indexed_at']) ? \Illuminate\Support\Carbon::parse($a['indexed_at'])->timestamp : 0;
                $dateB = isset($b['indexed_at']) ? \Illuminate\Support\Carbon::parse($b['indexed_at'])->timestamp : 0;
                return $dateB <=> $dateA;
            });

            $cacheFile = "cache/{$category}.json";
            $mergedData = [
                'meta' => [
                    'category' => $category,
                    'generated_at' => now()->toIso8601String(),
                    'record_count' => count($finalRecords),
                    'schema_version' => config('indexer.schema_version', '1.0'),
                ],
                'records' => $finalRecords,
            ];

            Storage::put($cacheFile, json_encode($mergedData, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
            $this->info("✓ {$category} cache updated with " . count($finalRecords) . " records");
        }

        $this->info('Cache refresh complete');

        return Command::SUCCESS;
    }
}
