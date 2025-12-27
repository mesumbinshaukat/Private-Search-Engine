<?php

namespace App\Console\Commands;

use App\Models\IndexMetadata;
use App\Services\StorageService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class UploadIndexCommand extends Command
{
    protected $signature = 'upload:index {--category=all : Category to upload} {--retry=3 : Number of retry attempts}';
    protected $description = 'Upload index files to Google Drive with retry logic';

    public function handle(StorageService $storage)
    {
        $category = $this->option('category');
        $maxRetries = (int) $this->option('retry');

        $query = IndexMetadata::pending();

        if ($category !== 'all') {
            $query->forCategory($category);
        }

        $indexes = $query->get();

        if ($indexes->isEmpty()) {
            $this->warn('No pending uploads found');
            return Command::SUCCESS;
        }

        $this->info("Uploading {$indexes->count()} indexes with up to {$maxRetries} retries per file...");

        $successCount = 0;
        $failedCount = 0;

        foreach ($indexes as $metadata) {
            $this->info("Uploading {$metadata->category} (ID: {$metadata->id})...");

            $uploaded = false;
            $lastError = null;

            // Retry logic
            for ($attempt = 1; $attempt <= $maxRetries; $attempt++) {
                try {
                    $result = $storage->uploadIndex($metadata);

                    if ($result['success']) {
                        $this->info("✓ {$metadata->category} uploaded successfully");
                        $successCount++;
                        $uploaded = true;
                        break;
                    } else {
                        $lastError = $result['error'] ?? 'Unknown error';
                        $this->warn("Attempt {$attempt}/{$maxRetries} failed: {$lastError}");
                    }
                } catch (\Exception $e) {
                    $lastError = $e->getMessage();
                    $this->warn("Attempt {$attempt}/{$maxRetries} exception: {$lastError}");
                    Log::error('Upload attempt failed', [
                        'category' => $metadata->category,
                        'attempt' => $attempt,
                        'error' => $lastError,
                        'trace' => $e->getTraceAsString(),
                    ]);
                }

                if ($attempt < $maxRetries) {
                    sleep(2); // Wait 2 seconds before retry
                }
            }

            if (!$uploaded) {
                $this->error("✗ {$metadata->category} failed after {$maxRetries} attempts: {$lastError}");
                $failedCount++;
                
                // Log critical failure
                Log::critical('Index upload failed after all retries', [
                    'category' => $metadata->category,
                    'metadata_id' => $metadata->id,
                    'file_path' => $metadata->file_path,
                    'last_error' => $lastError,
                ]);
            }
        }

        $this->info("Upload complete: {$successCount} succeeded, {$failedCount} failed");

        return $failedCount > 0 ? Command::FAILURE : Command::SUCCESS;
    }
}
