<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class MockGoogleDriveService
{
    private string $mockPath;

    public function __construct()
    {
        $this->mockPath = config('filesystems.disks.google_drive_mock.root', storage_path('app/google_drive_mock'));
        
        if (!file_exists($this->mockPath)) {
            mkdir($this->mockPath, 0755, true);
        }
    }

    public function upload(string $localPath, string $remoteName): array
    {
        try {
            $content = Storage::get($localPath);
            $mockFileId = 'mock_' . md5($remoteName . time());
            $mockFilePath = $this->mockPath . '/' . $mockFileId . '_' . basename($remoteName);

            file_put_contents($mockFilePath, $content);

            Log::info('Mock Google Drive upload', [
                'local_path' => $localPath,
                'remote_name' => $remoteName,
                'mock_file_id' => $mockFileId,
                'mock_file_path' => $mockFilePath,
            ]);

            return [
                'success' => true,
                'file_id' => $mockFileId,
                'name' => $remoteName,
                'size' => strlen($content),
            ];

        } catch (\Exception $e) {
            Log::error('Mock Google Drive upload failed', [
                'error' => $e->getMessage(),
            ]);

            return [
                'success' => false,
                'error' => $e->getMessage(),
            ];
        }
    }

    public function download(string $fileId, string $localPath): bool
    {
        try {
            $files = glob($this->mockPath . '/' . $fileId . '_*');

            if (empty($files)) {
                Log::error('Mock file not found', ['file_id' => $fileId]);
                return false;
            }

            $mockFilePath = $files[0];
            $content = file_get_contents($mockFilePath);

            Storage::put($localPath, $content);

            Log::info('Mock Google Drive download', [
                'file_id' => $fileId,
                'local_path' => $localPath,
            ]);

            return true;

        } catch (\Exception $e) {
            Log::error('Mock Google Drive download failed', [
                'error' => $e->getMessage(),
            ]);

            return false;
        }
    }

    public function verify(string $fileId, string $expectedChecksum): bool
    {
        try {
            $files = glob($this->mockPath . '/' . $fileId . '_*');

            if (empty($files)) {
                return false;
            }

            $mockFilePath = $files[0];
            $content = file_get_contents($mockFilePath);
            $actualChecksum = hash('sha256', $content);

            return $actualChecksum === $expectedChecksum;

        } catch (\Exception $e) {
            Log::error('Mock Google Drive verify failed', [
                'error' => $e->getMessage(),
            ]);

            return false;
        }
    }

    public function listFiles(string $query): array
    {
        // Simple mock implementation: list all files in mock path and filter by query if it mentions name
        $files = glob($this->mockPath . '/*');
        $result = [];

        foreach ($files as $file) {
            $name = basename($file);
            // If query contains name = '...', try to extract name
            if (preg_match("/name = '([^']+)'/", $query, $matches)) {
                if (str_contains($name, $matches[1])) {
                    $result[] = (object)[
                        'id' => strtok($name, '_'),
                        'name' => substr($name, strpos($name, '_') + 1),
                        'md5Checksum' => md5_file($file)
                    ];
                }
            } else {
                $result[] = (object)[
                    'id' => strtok($name, '_'),
                    'name' => substr($name, strpos($name, '_') + 1),
                    'md5Checksum' => md5_file($file)
                ];
            }
        }

        return $result;
    }
}
