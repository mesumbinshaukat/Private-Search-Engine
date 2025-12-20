<?php

require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Services\StorageService;
use Illuminate\Support\Facades\Storage;

$storage = app(StorageService::class);
$category = 'technology';

echo "Searching for {$category} files in folder ID: " . config('services.google_drive.folder_id') . "\n";
$files = $storage->listFiles("name contains '{$category}_' and name contains '.json'");

if (empty($files)) {
    echo "NO FILES FOUND. Check folder ID and permissions.\n";
    exit;
}

foreach ($files as $file) {
    echo "File found: {$file->name} (ID: {$file->id})\n";
    
    if (str_contains($file->name, '2025-12-20')) {
        echo "Downloading {$file->name}...\n";
        $tempPath = "temp/inspect_" . $file->name;
        if ($storage->downloadIndex($file->id, $tempPath)) {
            $content = Storage::get($tempPath);
            $data = json_decode($content, true);
            if ($data) {
                echo "DOWNLOAD SUCCESS\n";
                echo "Meta category: " . ($data['meta']['category'] ?? 'N/A') . "\n";
                echo "Record count in file: " . (isset($data['records']) ? count($data['records']) : 0) . "\n";
                echo "File size in Storage: " . Storage::size($tempPath) . " bytes\n";
                
                // Inspect first record
                if (!empty($data['records'])) {
                    echo "First record sample:\n";
                    print_r(array_slice($data['records'][0], 0, 5));
                }
            } else {
                echo "JSON DECODE FAILED\n";
            }
        } else {
            echo "DOWNLOAD FAILED\n";
        }
    }
}
