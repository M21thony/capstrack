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
    /** A folder in Drive is a file that happens to hold others. */
    public const FOLDER_MIME = 'application/vnd.google-apps.folder';

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

        $this->storeToken($userId, $token);
    }

    /**
     * Persist an already-fetched Google token response (shape: access_token,
     * refresh_token, expires_in, token_type, ...) as this user's Drive
     * connection. Shared by the dedicated "Connect Google Drive" callback
     * above and by AuthController's login callback, which requests the same
     * drive.file scope up front so most users never need the separate
     * connect flow at all.
     */
    public function storeToken(int $userId, array $token): void
    {
        $existing = GoogleDriveToken::where('user_id', $userId)->first();

        GoogleDriveToken::updateOrCreate(
            ['user_id' => $userId],
            [
                'access_token' => json_encode($token),
                // Google only sends refresh_token on first consent (or when
                // prompt=consent is forced); keep the old one if it's missing
                // this time rather than overwriting a working connection.
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
                // The refresh token itself is dead (expired, revoked, or the
                // OAuth consent screen is still in "Testing" mode, where
                // Google expires refresh tokens after 7 days). Clear the
                // stored connection so isConnected() — and the "Connect
                // Google Drive" banner — reflect reality instead of showing
                // "connected" for a token that no longer works.
                $record->delete();

                throw new \Exception('Failed to refresh Google Drive token: '.($newToken['error_description'] ?? $newToken['error']).' Please reconnect Google Drive.');
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

    /**
     * The files directly inside a folder the user picked.
     *
     * Whether this is allowed at all is the open question with the drive.file
     * scope: Google grants access to what the user hands over through the
     * Picker, and their documentation does not say whether handing over a
     * folder hands over its contents. So this returns whatever comes back and
     * the caller says something useful when that is nothing - rather than us
     * guessing in advance and being wrong in either direction.
     *
     * Sub-folders are not followed. One level is what a CaPSA means by "this
     * folder", and recursing invites importing someone's whole Drive.
     *
     * @return array<int, array{id: string, name: string, mime_type: string, size: ?int, web_view_link: ?string}>
     */
    public function listFolderChildren(int $userId, string $folderId): array
    {
        $client = $this->clientForUser($userId);
        $service = new GoogleDrive($client);

        $response = $service->files->listFiles([
            'q' => sprintf("'%s' in parents and trashed = false", $folderId),
            'fields' => 'files(id, name, mimeType, size, webViewLink)',
            'pageSize' => 100,
        ]);

        $out = [];

        foreach ($response->getFiles() as $file) {
            if ($file->getMimeType() === self::FOLDER_MIME) {
                continue;
            }

            $out[] = [
                'id' => $file->getId(),
                'name' => $file->getName(),
                'mime_type' => $file->getMimeType(),
                'size' => $file->getSize() ? (int) $file->getSize() : null,
                'web_view_link' => $file->getWebViewLink(),
            ];
        }

        return $out;
    }

    /**
     * Take on a file the user already owns, picked through the Google Picker.
     *
     * Nothing is copied: the file stays where it is and we record where to
     * find it. The one change made to it is the sharing - students open these
     * with no Drive access of their own, which is why uploads are made
     * link-viewable, and an imported file is not until this runs. That is a
     * real change to a file someone else owns, so the page says so before
     * asking for it.
     *
     * @return array{id: string, name: string, mime_type: string, size: ?int, web_view_link: ?string}
     */
    public function importFile(int $userId, string $fileId): array
    {
        $client = $this->clientForUser($userId);
        $service = new GoogleDrive($client);

        $file = $service->files->get($fileId, [
            'fields' => 'id, name, mimeType, size, webViewLink',
            // The picker offers shared drives too; without this their files are "not found".
            'supportsAllDrives' => true,
        ]);

        $service->permissions->create($fileId, new Permission([
            'type' => 'anyone',
            'role' => 'reader',
        ]), ['supportsAllDrives' => true]);

        return [
            'id' => $file->getId(),
            'name' => $file->getName(),
            'mime_type' => $file->getMimeType(),
            // A native Google Doc has no byte size and no extension; the link
            // is the whole of it.
            'size' => $file->getSize() ? (int) $file->getSize() : null,
            'web_view_link' => $file->getWebViewLink(),
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

    /**
     * Download a file's raw bytes from the user's Google Drive.
     *
     * @return string Binary file contents
     */
    public function downloadFile(int $userId, string $fileId): string
    {
        $client = $this->clientForUser($userId);
        $service = new GoogleDrive($client);

        $response = $service->files->get($fileId, ['alt' => 'media']);

        return $response->getBody()->getContents();
    }

    /**
     * Upload fresh content to an existing Drive file (keeps the same file id/link).
     */
    public function updateFile(int $userId, string $fileId, string $contents, string $mimeType = 'application/pdf'): void
    {
        $client = $this->clientForUser($userId);
        $service = new GoogleDrive($client);

        $service->files->update($fileId, new DriveFile, [
            'data' => $contents,
            'mimeType' => $mimeType,
            'uploadType' => 'multipart',
        ]);
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
        $root = $this->getOrCreateRootFolder($ownerExpertId);

        // Team subfolder inside the root, for this owner
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

    /**
     * The "CapsTRACK Submissions" root folder every other submission folder
     * (per-team auto-folders, and the named folders below) lives under, for
     * a given owner (expert_id).
     */
    private function getOrCreateRootFolder(int $ownerExpertId): AdminDriveFolder
    {
        $root = AdminDriveFolder::where('created_by', $ownerExpertId)
            ->whereNull('team_id')
            ->whereNull('parent_folder_id')
            ->where('name', 'CapsTRACK Submissions')
            ->first();

        if ($root) {
            return $root;
        }

        $created = $this->createFolder($ownerExpertId, 'CapsTRACK Submissions');

        return AdminDriveFolder::create([
            'name' => $created['name'],
            'drive_folder_id' => $created['id'],
            'created_by' => $ownerExpertId,
            'team_id' => null,
            'parent_folder_id' => null,
        ]);
    }

    /**
     * A folder an owner (typically the Subject Adviser) names explicitly
     * through the app, for students to pick from at submission time -
     * distinct from the auto-created per-team folders above, which the
     * student never sees or chooses. Not deduplicated by name: an owner
     * naming two folders the same thing gets two folders, same as if they
     * did it by hand in Drive.
     */
    public function createNamedFolder(int $ownerExpertId, string $name): AdminDriveFolder
    {
        $root = $this->getOrCreateRootFolder($ownerExpertId);

        $created = $this->createFolder($ownerExpertId, $name, $root->drive_folder_id);

        return AdminDriveFolder::create([
            'name' => $created['name'],
            'drive_folder_id' => $created['id'],
            'created_by' => $ownerExpertId,
            'team_id' => null,
            'parent_folder_id' => $root->id,
        ]);
    }

    /**
     * The owner's named, student-pickable folders (see createNamedFolder) -
     * excludes the root itself and the auto-created per-team folders.
     */
    public function listNamedFolders(int $ownerExpertId)
    {
        $root = AdminDriveFolder::where('created_by', $ownerExpertId)
            ->whereNull('team_id')
            ->whereNull('parent_folder_id')
            ->where('name', 'CapsTRACK Submissions')
            ->first();

        if (! $root) {
            return collect();
        }

        return AdminDriveFolder::where('created_by', $ownerExpertId)
            ->where('parent_folder_id', $root->id)
            ->whereNull('team_id')
            ->orderBy('name')
            ->get(['id', 'name', 'drive_folder_id']);
    }

    /**
     * Deletes a named folder from both Drive and our own tracking table.
     * Scoped to $ownerExpertId so one owner can't delete another's folder
     * by guessing an id.
     */
    public function deleteNamedFolder(int $ownerExpertId, int $folderRecordId): bool
    {
        $folder = AdminDriveFolder::where('id', $folderRecordId)
            ->where('created_by', $ownerExpertId)
            ->whereNull('team_id')
            ->first();

        if (! $folder) {
            return false;
        }

        $this->deleteFile($ownerExpertId, $folder->drive_folder_id);
        $folder->delete();

        return true;
    }
}
