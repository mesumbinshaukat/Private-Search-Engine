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
                $verified = $this->googleDrive->verify($result['file_id'], $metadata->checksum);

                if ($verified) {
                    $metadata->update([
                        'google_drive_file_id' => $result['file_id'],
                        'uploaded_at' => now(),
                    ]);

                    Log::info('Index uploaded successfully', [
                        'category' => $metadata->category,
                        'file_id' => $result['file_id'],
                    ]);

                    return [
                        'success' => true,
                        'file_id' => $result['file_id'],
                    ];
                } else {
                    Log::error('Upload verification failed', [
                        'category' => $metadata->category,
                    ]);

                    return [
                        'success' => false,
                        'error' => 'Upload verification failed',
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
}
