<?php

namespace App\Services;

use App\Models\IndexMetadata;
use App\Models\ParsedRecord;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class IndexerService
{
    protected $storage;

    public function __construct(StorageService $storage)
    {
        $this->storage = $storage;
    }

    public function generateIndex(string $category, ?string $date = null): array
    {
        $date = $date ?? now()->format('Y-m-d');
        $maxAgeDays = config('indexer.max_data_age_days', 5);
        $minRecords = config('indexer.min_records_per_category', 10);

        // 1. Fetch existing records from Google Drive for this category
        $existingRecords = $this->fetchExistingRecordsFromDrive($category);

        // 2. Fetch new records from local DB
        $newRecords = ParsedRecord::forCategory($category)
            ->newerThan($maxAgeDays)
            ->get();

        // 3. Merge and Deduplicate
        $mergedRecords = $this->mergeAndDeduplicate($existingRecords, $newRecords);

        // 4. Enforce 5-day age
        $cutoffDate = now()->subDays($maxAgeDays);
        $mergedRecords = array_filter($mergedRecords, function ($record) use ($cutoffDate) {
            $indexedAt = isset($record['indexed_at']) ? \Illuminate\Support\Carbon::parse($record['indexed_at']) : null;
            return $indexedAt && $indexedAt->greaterThanOrEqualTo($cutoffDate);
        });

        // 5. Check if we actually have new records to upload
        $newFound = false;
        $existingHashes = array_column($existingRecords, 'content_hash');
        foreach ($newRecords as $record) {
            if (!in_array($record->content_hash, $existingHashes)) {
                $newFound = true;
                break;
            }
        }

        if (!$newFound) {
            Log::info('No new records found for category, skipping index generation', ['category' => $category]);
            return [
                'success' => false,
                'error' => 'No new records',
                'category' => $category,
            ];
        }

        if (count($mergedRecords) < $minRecords) {
            Log::error('Insufficient records for category', [
                'category' => $category,
                'count' => count($mergedRecords),
                'minimum' => $minRecords,
            ]);

            return [
                'success' => false,
                'error' => 'Insufficient records',
                'count' => count($mergedRecords),
                'minimum' => $minRecords,
            ];
        }

        $jsonData = $this->buildJsonDataFromMerged($category, $mergedRecords);
        $jsonString = $this->generateDeterministicJson($jsonData);
        $checksum = hash(config('indexer.checksum_algorithm', 'sha256'), $jsonString);
        
        // Handle timestamped filename if date is today and file exists
        $filename = "index/{$category}_{$date}.json";
        if ($date === now()->format('Y-m-d') && $this->remoteFileExists($category, $date)) {
            $filename = "index/{$category}_{$date}_" . now()->format('Hi') . ".json";
        }

        Storage::put($filename, $jsonString);

        $metadata = IndexMetadata::create([
            'category' => $category,
            'date' => $date,
            'record_count' => count($mergedRecords),
            'file_path' => $filename,
            'checksum' => $checksum,
        ]);

        Log::info('Index generated', [
            'category' => $category,
            'date' => $date,
            'record_count' => count($mergedRecords),
            'filename' => $filename,
        ]);

        return [
            'success' => true,
            'category' => $category,
            'date' => $date,
            'record_count' => count($mergedRecords),
            'filename' => $filename,
            'checksum' => $checksum,
            'metadata_id' => $metadata->id,
        ];
    }

    private function fetchExistingRecordsFromDrive(string $category): array
    {
        $allRecords = [];
        // Query Drive for all JSONs belonging to this category
        $files = $this->storage->listFiles("name contains '{$category}_' and name contains '.json'");
        
        foreach ($files as $file) {
            $tempPath = "temp/" . $file->name;
            if ($this->storage->downloadIndex($file->id, $tempPath)) {
                $content = Storage::get($tempPath);
                $data = json_decode($content, true);
                if ($data && isset($data['records'])) {
                    $allRecords = array_merge($allRecords, $data['records']);
                }
                Storage::delete($tempPath);
            }
        }

        return $allRecords;
    }

    private function mergeAndDeduplicate(array $existing, $new): array
    {
        $merged = [];
        $seenUrls = [];
        $seenHashes = [];

        // Add existing first (should be deduplicated already but just in case)
        foreach ($existing as $record) {
            $url = $record['url'] ?? null;
            $hash = $record['content_hash'] ?? null;
            if ($url && !isset($seenUrls[$url]) && $hash && !isset($seenHashes[$hash])) {
                $merged[] = $record;
                $seenUrls[$url] = true;
                $seenHashes[$hash] = true;
            }
        }

        // Add new
        foreach ($new as $record) {
            if (!isset($seenUrls[$record->canonical_url]) && !isset($seenHashes[$record->content_hash])) {
                $merged[] = [
                    'title' => $record->title,
                    'url' => $record->canonical_url,
                    'description' => $record->description,
                    'published_at' => $record->published_at?->toIso8601String(),
                    'indexed_at' => $record->parsed_at->toIso8601String(),
                    'content_hash' => $record->content_hash,
                ];
                $seenUrls[$record->canonical_url] = true;
                $seenHashes[$record->content_hash] = true;
            }
        }

        return $merged;
    }

    private function buildJsonDataFromMerged(string $category, array $records): array
    {
        $maxAgeDays = config('indexer.max_data_age_days', 5);
        $validFrom = now()->subDays($maxAgeDays)->toIso8601String();
        $validUntil = now()->addDay()->toIso8601String();

        usort($records, function ($a, $b) {
            return strcmp($a['url'], $b['url']);
        });

        return [
            'meta' => [
                'category' => $category,
                'generated_at' => now()->toIso8601String(),
                'record_count' => count($records),
                'schema_version' => config('indexer.schema_version', '1.0'),
                'valid_from' => $validFrom,
                'valid_until' => $validUntil,
            ],
            'records' => $records,
        ];
    }

    private function remoteFileExists(string $category, string $date): bool
    {
        $query = "name = '{$category}_{$date}.json'";
        $files = $this->storage->listFiles($query);
        return count($files) > 0;
    }

    private function generateDeterministicJson(array $data): string
    {
        $options = JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE;

        if (config('indexer.json_pretty_print', true)) {
            $options |= JSON_PRETTY_PRINT;
        }

        return json_encode($data, $options);
    }

    public function cleanupOldRecords(): int
    {
        $maxAgeDays = config('indexer.max_data_age_days', 5);
        
        $deleted = ParsedRecord::olderThan($maxAgeDays)->delete();

        Log::info('Cleaned up old records', ['deleted' => $deleted]);

        return $deleted;
    }
}
