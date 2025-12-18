<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Google\Client;
use Google\Service\Drive;
use Illuminate\Support\Facades\Log;

class AuthorizeGoogleDriveCommand extends Command
{
    protected $signature = 'google-drive:authorize';
    protected $description = 'Authorize with Google Drive API using OAuth 2.0 Desktop Credentials';

    public function handle()
    {
        $clientSecretPath = config('services.google_drive.client_secret_json');
        $tokenPath = config('services.google_drive.token_json');

        if (!$clientSecretPath) {
            $this->error('GOOGLE_DRIVE_CLIENT_SECRET_JSON not configured in .env');
            return 1;
        }

        $secretFullPath = storage_path('app/' . ltrim($clientSecretPath, 'storage/app/'));
        $tokenFullPath = storage_path('app/' . ltrim($tokenPath, 'storage/app/'));

        if (!file_exists($secretFullPath)) {
            $this->error("Client secret JSON file not found: {$secretFullPath}");
            return 1;
        }

        $client = new Client();
        $client->setAuthConfig($secretFullPath);
        $client->addScope(Drive::DRIVE_FILE);
        $client->addScope(Drive::DRIVE);
        $client->setAccessType('offline');
        $client->setPrompt('select_account consent');

        $authUrl = $client->createAuthUrl();

        $this->info('Please visit the following URL to authorize the application:');
        $this->line($authUrl);
        $this->newLine();
        $this->warn('NOTE: If you are redirected to a page that says "Object not found" or a local server (like XAMPP),');
        $this->warn('do NOT worry. Look at the URL in your browser address bar.');
        $this->warn('Copy the characters after "code=" (up to the next "&" or the end of the URL).');
        $authCode = $this->ask('Enter the authorization code here');

        if (!$authCode) {
            $this->error('Authorization code is required');
            return 1;
        }

        try {
            $accessToken = $client->fetchAccessTokenWithAuthCode($authCode);

            if (isset($accessToken['error'])) {
                $this->error('Error fetching access token: ' . $accessToken['error_description']);
                return 1;
            }

            if (!is_dir(dirname($tokenFullPath))) {
                mkdir(dirname($tokenFullPath), 0755, true);
            }

            file_put_contents($tokenFullPath, json_encode($accessToken));

            $this->info('Authorization successful! Token saved to: ' . $tokenPath);
            Log::info('Google Drive API authorized successfully');

        } catch (\Exception $e) {
            $this->error('Authorization failed: ' . $e->getMessage());
            Log::error('Google Drive authorization failed', ['error' => $e->getMessage()]);
            return 1;
        }

        return 0;
    }
}
