<?php

namespace App\Console\Commands;

use App\Models\ParsedRecord;
use App\Models\Url;
use App\Models\Document;
use App\Services\UrlNormalizerService;
use App\Services\IndexEngineService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class MigrateParsedRecordsCommand extends Command
{
    protected $signature = 'migrate:parsed-to-documents 
                            {--dry-run : Run without making changes}
                            {--limit= : Limit number of records to migrate}';

    protected $description = 'Migrate ParsedRecords to new Documents/URLs system';

    private UrlNormalizerService $urlNormalizer;
    private IndexEngineService $indexEngine;

    public function __construct(
        UrlNormalizerService $urlNormalizer,
        IndexEngineService $indexEngine
    ) {
        parent::__construct();
        $this->urlNormalizer = $urlNormalizer;
        $this->indexEngine = $indexEngine;
    }

    public function handle()
    {
        $this->info('🔄 Migrating ParsedRecords to Documents/URLs system...');
        $this->newLine();

        $isDryRun = $this->option('dry-run');
        $limit = $this->option('limit');

        // Get records to migrate
        $query = ParsedRecord::query();
        if ($limit) {
            $query->limit((int)$limit);
        }
        
        $records = $query->get();
        $total = $records->count();

        if ($total === 0) {
            $this->warn('No ParsedRecords found to migrate.');
            return 0;
        }

        $this->info("Found {$total} records to migrate");
        if ($isDryRun) {
            $this->warn('DRY RUN MODE - No changes will be made');
        }
        $this->newLine();

        $migrated = 0;
        $skipped = 0;
        $failed = 0;

        $bar = $this->output->createProgressBar($total);
        $bar->start();

        foreach ($records as $record) {
            try {
                if ($isDryRun) {
                    // Just validate
                    $this->validateRecord($record);
                    $migrated++;
                } else {
                    // Actually migrate
                    $this->migrateRecord($record);
                    $migrated++;
                }
            } catch (\Exception $e) {
                $failed++;
                $this->newLine();
                $this->error("Failed to migrate record ID {$record->id}: {$e->getMessage()}");
            }

            $bar->advance();
        }

        $bar->finish();
        $this->newLine(2);

        // Summary
        $this->info('✅ Migration Complete!');
        $this->table(
            ['Status', 'Count'],
            [
                ['Migrated', $migrated],
                ['Skipped', $skipped],
                ['Failed', $failed],
                ['Total', $total]
            ]
        );

        if ($isDryRun) {
            $this->newLine();
            $this->info('Run without --dry-run to perform actual migration');
        }

        return 0;
    }

    private function validateRecord(ParsedRecord $record): void
    {
        if (empty($record->url)) {
            throw new \Exception('URL is empty');
        }

        if (empty($record->title)) {
            throw new \Exception('Title is empty');
        }
    }

    private function migrateRecord(ParsedRecord $record): void
    {
        DB::transaction(function () use ($record) {
            // Normalize URL
            $normalized = $this->urlNormalizer->normalize($record->canonical_url ?? $record->url);

            // Check if URL already exists
            $url = Url::where('url_hash', $normalized['hash'])->first();

            if (!$url) {
                // Create new URL
                $url = Url::create([
                    'original_url' => $record->canonical_url ?? $record->url,
                    'normalized_url' => $normalized['normalized'],
                    'url_hash' => $normalized['hash'],
                    'category' => $record->category,
                    'depth' => 0,
                    'priority' => 1.0,
                    'status' => 'crawled',
                    'last_crawled_at' => $record->parsed_at ?? now(),
                ]);
            }

            // Check if document already exists
            if ($url->document) {
                return; // Already migrated
            }

            // Create document
            $document = Document::create([
                'url_id' => $url->id,
                'title' => $record->title,
                'description' => $record->description,
                'content' => $record->description, // Use description as content
                'word_count' => str_word_count($record->description ?? ''),
                'language' => 'en',
                'indexed_at' => $record->parsed_at ?? now(),
            ]);

            // Index the document
            $this->indexEngine->indexDocument(
                $url->id,
                $record->title,
                $record->description ?? ''
            );
        });
    }
}
