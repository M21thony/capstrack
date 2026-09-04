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
        $request->validate([
            'file' => [
                'required',
                'file',
                'max:25600', // 25 MB
                function ($attribute, $value, $fail) {
                    $allowedExtensions = ['pdf', 'doc', 'docx', 'ppt', 'pptx'];
                    $ext = strtolower($value->getClientOriginalExtension());
                    if (! in_array($ext, $allowedExtensions)) {
                        $fail('The file must be a PDF, DOC, DOCX, PPT, or PPTX.');
                    }
                },
            ],
            'title' => 'required|string|max:255',
            'category' => 'required|string|max:50',
            'resource_for' => 'nullable|in:BSIT,BSCS,BLIS,BSIS',
            'drive_folder_id' => 'nullable|string',
        ]);

        try {
            $file = $request->file('file');
            $category = $request->input('category');

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
                'resource_for' => $request->input('resource_for'), // null = all programs
                'category' => $category,
                'title' => $request->input('title'),
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
                'downloads' => 0,
                'uploaded_at' => now(),
                'uploaded_by' => Auth::guard('expert')->id(),
            ]);

            return response()->json(['success' => true, 'resource' => $resource]);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 422);
        }
    }

    public function trackDownload(Resource $resource)
    {
        $resource->increment('downloads');

        return response()->json(['success' => true, 'downloads' => $resource->downloads]);
    }

    public function destroy(Resource $resource)
    {
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
