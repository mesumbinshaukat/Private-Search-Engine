<?php

namespace App\Services;

use App\Models\IndexMetadata;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class StorageService
{
    private $googleDrive;

    public function __construct()
    {
        $useMock = config('filesystems.disks.google_drive_mock.enabled', env('GOOGLE_DRIVE_MOCK', true));
        
        if ($useMock) {
            $this->googleDrive = app(MockGoogleDriveService::class);
        } else {
            $this->googleDrive = app(GoogleDriveService::class);
        }
    }

    public function uploadIndex(IndexMetadata $metadata): array
    {
        try {
            if (!Storage::exists($metadata->file_path)) {
                return [
                    'success' => false,
                    'error' => 'Index file not found',
                ];
            }

            $remoteName = basename($metadata->file_path);
            $result = $this->googleDrive->upload($metadata->file_path, $remoteName);

            if ($result['success']) {
                $fileId = $result['file_id'];
                $verified = false;
                $maxRetries = 3;
                $retryDelay = 2; // seconds

                for ($i = 0; $i < $maxRetries; $i++) {
                    if ($i > 0) {
                        sleep($retryDelay);
                        Log::info("Retrying verification for {$metadata->category} (attempt " . ($i + 1) . ")");
                    }

                    if ($this->googleDrive->verify($fileId, $metadata->checksum)) {
                        $verified = true;
                        break;
                    }
                }

                if ($verified) {
                    $metadata->update([
                        'google_drive_file_id' => $fileId,
                        'uploaded_at' => now(),
                    ]);

                    Log::info('Index uploaded and verified successfully', [
                        'category' => $metadata->category,
                        'file_id' => $fileId,
                    ]);

                    // Automatically cleanup local file after successful upload
                    Storage::delete($metadata->file_path);

                    return [
                        'success' => true,
                        'file_id' => $fileId,
                    ];
                } else {
                    Log::error('Upload verification failed after retries', [
                        'category' => $metadata->category,
                        'file_id' => $fileId,
                    ]);

                    return [
                        'success' => false,
                        'error' => 'Upload verification failed after retries',
                    ];
                }
            }

            return $result;

        } catch (\Exception $e) {
            Log::error('Upload exception', [
                'category' => $metadata->category,
                'error' => $e->getMessage(),
            ]);

            return [
                'success' => false,
                'error' => $e->getMessage(),
            ];
        }
    }

    public function downloadIndex(string $fileId, string $localPath): bool
    {
        try {
            return $this->googleDrive->download($fileId, $localPath);
        } catch (\Exception $e) {
            Log::error('Download exception', [
                'file_id' => $fileId,
                'error' => $e->getMessage(),
            ]);

            return false;
        }
    }

    public function listFiles(string $query): array
    {
        return $this->googleDrive->listFiles($query);
    }
}
