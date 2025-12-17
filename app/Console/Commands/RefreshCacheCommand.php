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

        $latestIndexes = IndexMetadata::uploaded()
            ->latest('uploaded_at')
            ->get()
            ->groupBy('category')
            ->map(fn($group) => $group->first());

        if ($latestIndexes->isEmpty()) {
            $this->warn('No uploaded indexes found');
            return Command::FAILURE;
        }

        foreach ($latestIndexes as $category => $metadata) {
            $this->info("Downloading {$category}...");

            $cacheFile = "cache/{$category}.json";
            
            if ($metadata->google_drive_file_id) {
                $success = $storage->downloadIndex($metadata->google_drive_file_id, $cacheFile);

                if ($success) {
                    $this->info("✓ {$category} cached");
                } else {
                    $this->error("✗ {$category} download failed");
                }
            } else {
                if (Storage::exists($metadata->file_path)) {
                    Storage::copy($metadata->file_path, $cacheFile);
                    $this->info("✓ {$category} cached from local");
                }
            }
        }

        $this->info('Cache refresh complete');

        return Command::SUCCESS;
    }
}
