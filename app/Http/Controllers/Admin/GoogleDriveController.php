<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AdminDriveFolder;
use App\Models\Resource;
use App\Services\GoogleDriveService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

class GoogleDriveController extends Controller
{
    public function __construct(private GoogleDriveService $drive) {}

    // ── Start OAuth flow ───────────────────────────────────────────────
    public function connect()
    {
        return redirect($this->drive->getAuthUrl());
    }

    // ── OAuth callback — Google redirects here after consent ───────────
    public function callback(Request $request)
    {
        $expert = Auth::guard('expert')->user();
        $route = $expert && $expert->is_admin ? 'capsa.resources' : 'instructor.docreview';

        if ($request->has('error')) {
            return redirect()->route($route)->with('error', 'Google Drive connection was cancelled.');
        }

        try {
            $expert = Auth::guard('expert')->user();
            $this->drive->handleCallback($request->get('code'), Auth::guard('expert')->id());

            $route = $expert && $expert->is_admin ? 'capsa.resources' : 'instructor.docreview';

            return redirect()->route($route)->with('success', 'Google Drive connected successfully.');
        } catch (\Exception $e) {
            Log::error('Google Drive callback failed', [
                'message' => $e->getMessage(),
                'user_id' => Auth::guard('expert')->id(),
                'trace' => $e->getTraceAsString(),
            ]);

            return redirect()->route('instructor.docreview')->with('error', 'Failed to connect Google Drive: '.$e->getMessage());
        }
    }

    public function status()
    {
        return response()->json([
            'connected' => $this->drive->isConnected(Auth::guard('expert')->id()),
        ]);
    }

    public function disconnect()
    {
        $this->drive->disconnect(Auth::guard('expert')->id());

        return response()->json(['success' => true]);
    }

    public function listFolders()
    {
        return response()->json([
            'folders' => AdminDriveFolder::orderBy('name')->get(['id', 'name', 'drive_folder_id']),
        ]);
    }

    public function createFolder(Request $request)
    {
        $request->validate(['name' => 'required|string|max:150']);

        try {
            $folder = $this->drive->createFolder(Auth::guard('expert')->id(), $request->input('name'));

            $record = AdminDriveFolder::create([
                'name' => $folder['name'],
                'drive_folder_id' => $folder['id'],
                'created_by' => Auth::guard('expert')->id(),
            ]);

            return response()->json(['success' => true, 'folder' => $record]);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 422);
        }
    }

    // ── Named "submission folders" — Subject Adviser names folders here so
    //    students can pick one at submission time, instead of everything
    //    landing in one fixed auto-created per-team folder. ────────────────
    public function listSubmissionFolders()
    {
        return response()->json([
            'folders' => $this->drive->listNamedFolders(Auth::guard('expert')->id()),
        ]);
    }

    public function storeSubmissionFolder(Request $request)
    {
        $request->validate(['name' => 'required|string|max:150']);

        try {
            $folder = $this->drive->createNamedFolder(Auth::guard('expert')->id(), $request->input('name'));

            return response()->json(['success' => true, 'folder' => $folder]);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 422);
        }
    }

    public function destroySubmissionFolder(int $id)
    {
        $deleted = $this->drive->deleteNamedFolder(Auth::guard('expert')->id(), $id);

        if (! $deleted) {
            return response()->json(['success' => false, 'message' => 'Folder not found.'], 404);
        }

        return response()->json(['success' => true]);
    }

    // ── Upload a file: pushes it to Drive, then saves a resources row ────
    public function upload(Request $request)
    {
        // A folder arrives as files[] - the browser has no way to send a
        // folder, it sends what is inside one - so the only difference here is
        // how many came, and where each title comes from.
        $isBatch = $request->hasFile('files');

        $isAllowedType = function ($attribute, $value, $fail) {
            $allowedExtensions = ['pdf', 'doc', 'docx', 'ppt', 'pptx'];
            $ext = strtolower($value->getClientOriginalExtension());
            if (! in_array($ext, $allowedExtensions)) {
                $fail('The file must be a PDF, DOC, DOCX, PPT, or PPTX.');
            }
        };

        $request->validate([
            'file' => [$isBatch ? 'nullable' : 'required', 'file', 'max:25600', $isAllowedType],

            // Capped because this runs while the CaPSA waits - two Drive calls
            // a file, no queue. Raise it when someone actually needs to.
            'files' => [$isBatch ? 'required' : 'nullable', 'array', 'max:25'],
            'files.*' => ['file', 'max:25600', $isAllowedType],

            // A folder names its own files, so a title is only asked for when
            // a single file was picked.
            'title' => [$isBatch ? 'nullable' : 'required', 'string', 'max:255'],
            'category' => 'required|string|max:50',
            // Only a program this CaPSA actually handles. Without this the
            // page could be scoped and a hand-made request still file a
            // resource under someone else's program. Null means every
            // program, which stays open.
            'resource_for' => [
                'nullable',
                'in:BSIT,BSCS,BLIS,BSIS',
                function ($attribute, $value, $fail) {
                    $viewer = Auth::guard('expert')->user();

                    if (! $value || ! $viewer || $viewer->is_admin) {
                        return;
                    }

                    if (! in_array($value, $viewer->handledPrograms(), true)) {
                        $fail('You can only upload resources for a program you handle.');
                    }
                },
            ],
            // The sections this is for, as labels. Leave it empty to reach
            // every section you handle - a resource with no targeting only
            // reaches your own students anyway, since that is who can see
            // your uploads.
            'sections' => ['nullable', 'array'],
            'sections.*' => [
                'string',
                'max:24',
                function ($attribute, $value, $fail) {
                    $viewer = Auth::guard('expert')->user();

                    if (! $viewer || $viewer->is_admin) {
                        return;
                    }

                    if (! in_array($value, $viewer->sections ?? [], true)) {
                        $fail('You can only upload resources for a section you handle.');
                    }
                },
            ],
            'drive_folder_id' => 'nullable|string',
        ]);

        try {
            $files = $isBatch
                ? array_values($request->file('files'))
                : [$request->file('file')];
            $category = $request->input('category');
            $sections = array_unique($request->input('sections', []));
            $created = [];

            // PHP's fileinfo-based MIME detection is unreliable for Office
            // files on some setups (e.g. .docx often detected as application/zip).
            // Map the extension to the correct MIME type explicitly instead
            // of trusting $file->getMimeType().
            $extensionMimeMap = [
                'pdf' => 'application/pdf',
                'doc' => 'application/msword',
                'docx' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
                'ppt' => 'application/vnd.ms-powerpoint',
                'pptx' => 'application/vnd.openxmlformats-officedocument.presentationml.presentation',
            ];
            foreach ($files as $file) {
                $ext = strtolower($file->getClientOriginalExtension());
                $correctMimeType = $extensionMimeMap[$ext] ?? $file->getMimeType();

                $uploaded = $this->drive->uploadFile(
                    Auth::guard('expert')->id(),
                    $file->getRealPath(),
                    $file->getClientOriginalName(),
                    $correctMimeType,
                    $request->input('drive_folder_id')
                );

                $resource = Resource::create([
                    'resource_type' => Resource::typeForCategory($category),
                    // Named sections carry the program in their labels, so the
                    // program column stays empty for those - one fact, one place.
                    'resource_for' => $request->filled('sections') ? null : $request->input('resource_for'),
                    'category' => $category,
                    'title' => $isBatch
                        ? self::titleFrom($file->getClientOriginalName())
                        : $request->input('title'),
                    'description' => $request->input('description'),
                    'file_name' => $file->getClientOriginalName(),
                    'file_path' => null, // real file lives on Drive; see drive_web_view_link
                    'file_size' => $file->getSize(),
                    'file_type' => $ext,
                    'content_type' => 'file',
                    'external_url' => null,
                    'is_downloadable' => true,
                    'drive_file_id' => $uploaded['id'],
                    'drive_folder_id' => $request->input('drive_folder_id'),
                    'drive_web_view_link' => $uploaded['web_view_link'],
                    'uploaded_at' => now(),
                    'uploaded_by' => Auth::guard('expert')->id(),
                ]);

                foreach ($sections as $section) {
                    $resource->sections()->create(['section' => $section]);
                }

                $created[] = $resource->load('sections');
            }

            return response()->json([
                'success' => true,
                'resources' => $created,
                // The single-file caller still reads this.
                'resource' => $created[0] ?? null,
            ]);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 422);
        }
    }

    /**
     * A readable title out of a filename, for files uploaded in bulk where
     * nobody typed one: "Chapter_1-3 Template.docx" -> "Chapter 1 3 Template".
     */
    private static function titleFrom(string $filename): string
    {
        $stem = pathinfo($filename, PATHINFO_FILENAME);

        return trim(preg_replace('/[-_]+/', ' ', $stem)) ?: $filename;
    }

    /**
     * Take on files the CaPSA already has in Drive, chosen through the Picker.
     *
     * Nothing is uploaded - each file stays where it is, and what is written
     * here is a row saying where to find it and who it is for. The category
     * and sections arrive per file because the review list lets them be set
     * per file, even when a folder was picked and they are all the same.
     */
    public function import(Request $request)
    {
        $viewer = Auth::guard('expert')->user();

        $validated = $request->validate([
            // Capped like the upload: two Drive calls a file, no queue, and
            // the CaPSA is watching.
            'files' => ['required', 'array', 'max:25'],
            'files.*.drive_file_id' => ['required', 'string', 'max:255'],
            'files.*.category' => ['required', 'string', 'max:50'],
            'files.*.sections' => ['nullable', 'array'],
            // The same rule the upload uses, for the same reason: a page can
            // be scoped and a hand-made request still file a resource under
            // someone else's section.
            'files.*.sections.*' => [
                'string',
                'max:24',
                function ($attribute, $value, $fail) use ($viewer) {
                    if (! $viewer || $viewer->is_admin) {
                        return;
                    }

                    if (! in_array($value, $viewer->sections ?? [], true)) {
                        $fail('You can only import resources for a section you handle.');
                    }
                },
            ],
        ]);

        $created = [];
        $skipped = [];

        try {
            foreach ($validated['files'] as $entry) {
                $driveFileId = $entry['drive_file_id'];

                // Importing the same file twice would give students two rows
                // pointing at one document.
                $already = Resource::where('drive_file_id', $driveFileId)
                    ->where('uploaded_by', $viewer->expert_id)
                    ->exists();

                if ($already) {
                    $skipped[] = $driveFileId;

                    continue;
                }

                $file = $this->drive->importFile($viewer->expert_id, $driveFileId);
                $category = $entry['category'];
                $sections = array_unique($entry['sections'] ?? []);

                $resource = Resource::create([
                    'resource_type' => Resource::typeForCategory($category),
                    // Named sections carry the program in their labels, so the
                    // program column stays empty for those - one fact, one place.
                    'resource_for' => null,
                    'category' => $category,
                    'title' => self::titleFrom($file['name']),
                    'file_name' => $file['name'],
                    'file_path' => null,
                    'file_size' => $file['size'],
                    'file_type' => self::extensionFor($file['name'], $file['mime_type']),
                    'content_type' => 'file',
                    'external_url' => null,
                    // A native Google Doc cannot be handed over as bytes, only
                    // opened, so it is not offered as a download.
                    'is_downloadable' => ! str_starts_with($file['mime_type'] ?? '', 'application/vnd.google-apps.'),
                    'drive_file_id' => $file['id'],
                    // The folder it sits in is the CaPSA's own, not one this
                    // app made, so there is no admin_drive_folders row to point at.
                    'drive_folder_id' => null,
                    'drive_web_view_link' => $file['web_view_link'],
                    'uploaded_at' => now(),
                    'uploaded_by' => $viewer->expert_id,
                ]);

                foreach ($sections as $section) {
                    $resource->sections()->create(['section' => $section]);
                }

                $created[] = $resource->load('sections');
            }

            return response()->json([
                'success' => true,
                'resources' => $created,
                'skipped' => count($skipped),
            ]);
        } catch (\Exception $e) {
            // Whatever was taken on before the failure stays - it is already
            // shared and recorded, and rolling it back would unshare a file
            // the CaPSA owns.
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
                'resources' => $created,
                'skipped' => count($skipped),
            ], 422);
        }
    }

    /**
     * The extension to record for a file, which a Drive file need not have:
     * a native Google Doc has a mime type and nothing else.
     */
    private static function extensionFor(string $filename, ?string $mimeType): string
    {
        $ext = strtolower(pathinfo($filename, PATHINFO_EXTENSION));

        if ($ext !== '') {
            return $ext;
        }

        return match ($mimeType) {
            'application/vnd.google-apps.document' => 'gdoc',
            'application/vnd.google-apps.spreadsheet' => 'gsheet',
            'application/vnd.google-apps.presentation' => 'gslides',
            default => 'file',
        };
    }

    public function destroy(Resource $resource)
    {
        $viewer = Auth::guard('expert')->user();

        // Deleting is scoped like seeing: your own uploads only. There was no
        // ownership check at all, so any CaPSA could remove anyone's.
        if ($viewer && ! $viewer->is_admin && (int) $resource->uploaded_by !== (int) $viewer->expert_id) {
            return response()->json([
                'success' => false,
                'message' => 'That resource was uploaded by someone else.',
            ], 403);
        }

        try {
            if ($resource->drive_file_id) {
                $this->drive->deleteFile(Auth::guard('expert')->id(), $resource->drive_file_id);
            }
            $resource->delete();

            return response()->json(['success' => true]);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 422);
        }
    }
}
