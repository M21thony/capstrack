<?php

namespace App\Services;

use App\Models\AdminDriveFolder;
use App\Models\GoogleDriveToken;
use Google\Client as GoogleClient;
use Google\Service\Drive as GoogleDrive;
use Google\Service\Drive\DriveFile;
use Google\Service\Drive\Permission;

class GoogleDriveService
{
    private GoogleClient $client;

    public function __construct()
    {
        $this->client = new GoogleClient;
        $this->client->setClientId(config('services.google.client_id'));
        $this->client->setClientSecret(config('services.google.client_secret'));
        $this->client->setRedirectUri(route('gdrive.callback'));

        $this->client->setScopes(['https://www.googleapis.com/auth/drive.file']);
        $this->client->setAccessType('offline');
        $this->client->setPrompt('consent'); // ensures a refresh_token is issued every time
    }

    public function getAuthUrl(): string
    {
        return $this->client->createAuthUrl();
    }

    public function handleCallback(string $code, int $userId): void
    {
        $token = $this->client->fetchAccessTokenWithAuthCode($code);

        if (isset($token['error'])) {
            throw new \Exception('Google Drive auth failed: '.($token['error_description'] ?? $token['error']));
        }

        $existing = GoogleDriveToken::where('user_id', $userId)->first();

        GoogleDriveToken::updateOrCreate(
            ['user_id' => $userId],
            [
                'access_token' => json_encode($token),
                // Google only sends refresh_token on first consent; keep the old one if it's missing this time.
                'refresh_token' => $token['refresh_token'] ?? optional($existing)->refresh_token,
                'expires_at' => now()->addSeconds($token['expires_in'] ?? 3600),
            ]
        );
    }

    public function isConnected(int $userId): bool
    {
        return GoogleDriveToken::where('user_id', $userId)->exists();
    }

    public function disconnect(int $userId): void
    {
        GoogleDriveToken::where('user_id', $userId)->delete();
    }

    private function clientForUser(int $userId): GoogleClient
    {
        $record = GoogleDriveToken::where('user_id', $userId)->first();

        if (! $record) {
            throw new \Exception('Google Drive is not connected for this account.');
        }

        $token = json_decode($record->access_token, true);
        $this->client->setAccessToken($token);

        if ($this->client->isAccessTokenExpired()) {
            $refreshToken = $record->refresh_token ?? ($token['refresh_token'] ?? null);

            if (! $refreshToken) {
                throw new \Exception('Google Drive session expired and no refresh token is available. Please reconnect.');
            }

            $newToken = $this->client->fetchAccessTokenWithRefreshToken($refreshToken);

            if (isset($newToken['error'])) {
                throw new \Exception('Failed to refresh Google Drive token: '.($newToken['error_description'] ?? $newToken['error']));
            }

            $newToken['refresh_token'] = $newToken['refresh_token'] ?? $refreshToken;

            $record->update([
                'access_token' => json_encode($newToken),
                'refresh_token' => $newToken['refresh_token'],
                'expires_at' => now()->addSeconds($newToken['expires_in'] ?? 3600),
            ]);

            $this->client->setAccessToken($newToken);
        }

        return $this->client;
    }

    public function createFolder(int $userId, string $name, ?string $parentId = null): array
    {
        $client = $this->clientForUser($userId);
        $service = new GoogleDrive($client);

        $fileMetadata = new DriveFile([
            'name' => $name,
            'mimeType' => 'application/vnd.google-apps.folder',
            'parents' => $parentId ? [$parentId] : [],
        ]);

        $folder = $service->files->create($fileMetadata, ['fields' => 'id, name']);

        return [
            'id' => $folder->getId(),
            'name' => $folder->getName(),
        ];
    }

    public function uploadFile(int $userId, string $localPath, string $filename, string $mimeType, ?string $folderId = null): array
    {
        $client = $this->clientForUser($userId);
        $service = new GoogleDrive($client);

        $fileMetadata = new DriveFile([
            'name' => $filename,
            'parents' => $folderId ? [$folderId] : [],
        ]);

        $content = file_get_contents($localPath);

        $file = $service->files->create($fileMetadata, [
            'data' => $content,
            'mimeType' => $mimeType,
            'uploadType' => 'multipart',
            'fields' => 'id, name, webViewLink, webContentLink, size',
        ]);

        // Make it viewable via link so students/experts can open it without
        // needing their own access to the admin's Drive.
        $service->permissions->create($file->getId(), new Permission([
            'type' => 'anyone',
            'role' => 'reader',
        ]));

        return [
            'id' => $file->getId(),
            'name' => $file->getName(),
            'web_view_link' => $file->getWebViewLink(),
            'web_content_link' => $file->getWebContentLink(),
        ];
    }

    public function deleteFile(int $userId, string $fileId): void
    {
        $client = $this->clientForUser($userId);
        $service = new GoogleDrive($client);
        $service->files->delete($fileId);
    }

    /**
     * Get or create the folder that a given owner (expert_id) uses to receive
     * a specific team's submissions in their own Drive. Reuses admin_drive_folders
     * for both admins (Subject Adviser) and regular experts (Adviser/Consultant/
     * Grammarian) since it's just keyed by created_by = owner's expert_id.
     *
     * Structure created on first use:
     *   "CapsTRACK Submissions"          (parent_folder_id = null, team_id = null)
     *   └── "Team {team_name}"           (parent_folder_id = root.id, team_id = X)
     */
    public function getOrCreateSubmissionFolder(int $ownerExpertId, int $teamId, string $teamName): string
    {
        // 1. Root "CapsTRACK Submissions" folder for this owner
        $root = AdminDriveFolder::where('created_by', $ownerExpertId)
            ->whereNull('team_id')
            ->whereNull('parent_folder_id')
            ->where('name', 'CapsTRACK Submissions')
            ->first();

        if (! $root) {
            $created = $this->createFolder($ownerExpertId, 'CapsTRACK Submissions');

            $root = AdminDriveFolder::create([
                'name' => $created['name'],
                'drive_folder_id' => $created['id'],
                'created_by' => $ownerExpertId,
                'team_id' => null,
                'parent_folder_id' => null,
            ]);
        }

        // 2. Team subfolder inside the root, for this owner
        $teamFolder = AdminDriveFolder::where('created_by', $ownerExpertId)
            ->where('team_id', $teamId)
            ->where('parent_folder_id', $root->id)
            ->first();

        if (! $teamFolder) {
            $folderName = 'Team '.$teamName;
            $created = $this->createFolder($ownerExpertId, $folderName, $root->drive_folder_id);

            $teamFolder = AdminDriveFolder::create([
                'name' => $created['name'],
                'drive_folder_id' => $created['id'],
                'created_by' => $ownerExpertId,
                'team_id' => $teamId,
                'parent_folder_id' => $root->id,
            ]);
        }

        return $teamFolder->drive_folder_id;
    }
}