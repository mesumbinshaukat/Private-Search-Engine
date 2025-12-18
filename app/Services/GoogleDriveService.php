<?php

namespace App\Services;

use Google\Client;
use Google\Service\Drive;
use Google\Service\Drive\DriveFile;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class GoogleDriveService
{
    private ?Drive $driveService = null;
    private string $folderId;

    public function __construct()
    {
        $this->folderId = config('services.google_drive.folder_id', '');
        $this->initializeClient();
    }

    private function initializeClient(): void
    {
        try {
            $clientSecretPath = config('services.google_drive.client_secret_json');
            $tokenPath = config('services.google_drive.token_json');

            if (!$clientSecretPath) {
                throw new \Exception('GOOGLE_DRIVE_CLIENT_SECRET_JSON not configured');
            }

            $secretFullPath = storage_path('app/' . ltrim($clientSecretPath, 'storage/app/'));
            $tokenFullPath = storage_path('app/' . ltrim($tokenPath, 'storage/app/'));

            if (!file_exists($secretFullPath)) {
                throw new \Exception("Client secret JSON file not found: {$secretFullPath}");
            }

            $client = new Client();
            $client->setAuthConfig($secretFullPath);
            $client->addScope(Drive::DRIVE_FILE);
            $client->addScope(Drive::DRIVE);
            $client->setAccessType('offline');
            $client->setPrompt('select_account consent');

            if (file_exists($tokenFullPath)) {
                $accessToken = json_decode(file_get_contents($tokenFullPath), true);
                $client->setAccessToken($accessToken);
            }

            if ($client->isAccessTokenExpired()) {
                if ($client->getRefreshToken()) {
                    $client->fetchAccessTokenWithRefreshToken($client->getRefreshToken());
                    file_put_contents($tokenFullPath, json_encode($client->getAccessToken()));
                } else {
                    Log::warning('Google Drive access token expired and no refresh token available');
                    $this->driveService = null;
                    return;
                }
            }

            $this->driveService = new Drive($client);

            Log::info('Google Drive client initialized with OAuth 2.0');

        } catch (\Exception $e) {
            Log::error('Failed to initialize Google Drive client', [
                'error' => $e->getMessage(),
            ]);

            $this->driveService = null;
        }
    }

    public function upload(string $localPath, string $remoteName): array
    {
        if (!$this->driveService) {
            return [
                'success' => false,
                'error' => 'Google Drive client not initialized',
            ];
        }

        try {
            if (!Storage::exists($localPath)) {
                return [
                    'success' => false,
                    'error' => 'Local file not found',
                ];
            }

            $content = Storage::get($localPath);
            $fullPath = Storage::path($localPath);

            $fileMetadata = new DriveFile([
                'name' => $remoteName,
                'parents' => [$this->folderId],
            ]);

            $file = $this->driveService->files->create(
                $fileMetadata,
                [
                    'data' => $content,
                    'mimeType' => 'application/json',
                    'uploadType' => 'multipart',
                    'fields' => 'id,name,size',
                ]
            );

            Log::info('Google Drive upload successful', [
                'local_path' => $localPath,
                'remote_name' => $remoteName,
                'file_id' => $file->id,
                'size' => $file->size,
            ]);

            return [
                'success' => true,
                'file_id' => $file->id,
                'name' => $file->name,
                'size' => $file->size,
            ];

        } catch (\Google\Service\Exception $e) {
            Log::error('Google Drive API error during upload', [
                'error' => $e->getMessage(),
                'code' => $e->getCode(),
            ]);

            return [
                'success' => false,
                'error' => 'Google Drive API error: ' . $e->getMessage(),
            ];

        } catch (\Exception $e) {
            Log::error('Google Drive upload failed', [
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
        if (!$this->driveService) {
            Log::error('Google Drive client not initialized for download');
            return false;
        }

        try {
            $response = $this->driveService->files->get($fileId, [
                'alt' => 'media',
            ]);

            $content = $response->getBody()->getContents();

            Storage::put($localPath, $content);

            Log::info('Google Drive download successful', [
                'file_id' => $fileId,
                'local_path' => $localPath,
                'size' => strlen($content),
            ]);

            return true;

        } catch (\Google\Service\Exception $e) {
            Log::error('Google Drive API error during download', [
                'file_id' => $fileId,
                'error' => $e->getMessage(),
                'code' => $e->getCode(),
            ]);

            return false;

        } catch (\Exception $e) {
            Log::error('Google Drive download failed', [
                'file_id' => $fileId,
                'error' => $e->getMessage(),
            ]);

            return false;
        }
    }

    public function verify(string $fileId, string $expectedChecksum): bool
    {
        if (!$this->driveService) {
            Log::error('Google Drive client not initialized for verification');
            return false;
        }

        try {
            $response = $this->driveService->files->get($fileId, [
                'alt' => 'media',
            ]);

            $content = $response->getBody()->getContents();
            $actualChecksum = hash('sha256', $content);

            $verified = $actualChecksum === $expectedChecksum;

            if ($verified) {
                Log::info('Google Drive file verification successful', [
                    'file_id' => $fileId,
                    'checksum' => $actualChecksum,
                ]);
            } else {
                Log::warning('Google Drive file verification failed', [
                    'file_id' => $fileId,
                    'expected' => $expectedChecksum,
                    'actual' => $actualChecksum,
                ]);
            }

            return $verified;

        } catch (\Exception $e) {
            Log::error('Google Drive verification failed', [
                'file_id' => $fileId,
                'error' => $e->getMessage(),
            ]);

            return false;
        }
    }

    public function isAvailable(): bool
    {
        return $this->driveService !== null;
    }
}
