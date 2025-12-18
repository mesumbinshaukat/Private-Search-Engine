<?php

namespace App\Services;

use App\Models\IndexMetadata;
use App\Models\ParsedRecord;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class IndexerService
{
    public function generateIndex(string $category, ?string $date = null): array
    {
        $date = $date ?? now()->format('Y-m-d');
        $maxAgeDays = config('indexer.max_data_age_days', 5);
        $minRecords = config('indexer.min_records_per_category', 10);

        $records = ParsedRecord::forCategory($category)
            ->newerThan($maxAgeDays)
            ->orderBy('parsed_at', 'desc')
            ->get();

        if ($records->count() < $minRecords) {
            Log::error('Insufficient records for category', [
                'category' => $category,
                'count' => $records->count(),
                'minimum' => $minRecords,
            ]);

            return [
                'success' => false,
                'error' => 'Insufficient records',
                'count' => $records->count(),
                'minimum' => $minRecords,
            ];
        }

        $jsonData = $this->buildJsonData($category, $records, $date);
        $jsonString = $this->generateDeterministicJson($jsonData);
        $checksum = hash(config('indexer.checksum_algorithm', 'sha256'), $jsonString);
        $filename = "index/{$category}_{$date}.json";

        Storage::put($filename, $jsonString);

        $metadata = IndexMetadata::where('category', $category)
            ->where('date', $date)
            ->first();

        if (!$metadata) {
            $metadata = new IndexMetadata();
            $metadata->category = $category;
            $metadata->date = $date;
        }

        $metadata->record_count = $records->count();
        $metadata->file_path = $filename;
        $metadata->checksum = $checksum;
        $metadata->save();

        Log::info('Index generated', [
            'category' => $category,
            'date' => $date,
            'record_count' => $records->count(),
            'filename' => $filename,
        ]);

        return [
            'success' => true,
            'category' => $category,
            'date' => $date,
            'record_count' => $records->count(),
            'filename' => $filename,
            'checksum' => $checksum,
            'metadata_id' => $metadata->id,
        ];
    }

    private function buildJsonData(string $category, $records, string $date): array
    {
        $maxAgeDays = config('indexer.max_data_age_days', 5);
        $validFrom = now()->subDays($maxAgeDays)->toIso8601String();
        $validUntil = now()->addDay()->toIso8601String();

        $recordsArray = $records->map(function ($record) {
            return [
                'title' => $record->title,
                'url' => $record->canonical_url,
                'description' => $record->description,
                'published_at' => $record->published_at?->toIso8601String(),
                'indexed_at' => $record->parsed_at->toIso8601String(),
            ];
        })->toArray();

        usort($recordsArray, function ($a, $b) {
            return strcmp($a['url'], $b['url']);
        });

        return [
            'meta' => [
                'category' => $category,
                'generated_at' => now()->toIso8601String(),
                'record_count' => count($recordsArray),
                'schema_version' => config('indexer.schema_version', '1.0'),
                'valid_from' => $validFrom,
                'valid_until' => $validUntil,
            ],
            'records' => $recordsArray,
        ];
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
