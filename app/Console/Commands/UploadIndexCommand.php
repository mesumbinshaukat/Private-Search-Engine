<?php

namespace App\Console\Commands;

use App\Models\IndexMetadata;
use App\Services\StorageService;
use Illuminate\Console\Command;

class UploadIndexCommand extends Command
{
    protected $signature = 'upload:index {--category=all : Category to upload}';
    protected $description = 'Upload index files to Google Drive';

    public function handle(StorageService $storage)
    {
        $category = $this->option('category');

        $query = IndexMetadata::pending();

        if ($category !== 'all') {
            $query->forCategory($category);
        }

        $indexes = $query->get();

        if ($indexes->isEmpty()) {
            $this->warn('No pending uploads found');
            return Command::SUCCESS;
        }

        $this->info("Uploading {$indexes->count()} indexes...");

        foreach ($indexes as $metadata) {
            $this->info("Uploading {$metadata->category}...");

            $result = $storage->uploadIndex($metadata);

            if ($result['success']) {
                $this->info("✓ {$metadata->category} uploaded");
            } else {
                $this->error("✗ {$metadata->category}: {$result['error']}");
            }
        }

        $this->info('Upload complete');

        return Command::SUCCESS;
    }
}
