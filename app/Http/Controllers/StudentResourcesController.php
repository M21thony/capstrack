<?php

namespace App\Http\Controllers;

use App\Models\AdminDriveFolder;
use App\Models\Resource;
use Illuminate\Support\Facades\Auth;

class StudentResourcesController extends Controller
{
    public function index()
    {
        $student = Auth::user();

        $resources = Resource::orderByDesc('uploaded_at')->get(['resource_id', 'title', 'category', 'resource_for', 'file_type', 'file_size', 'drive_web_view_link', 'drive_file_id', 'drive_folder_id', 'downloads', 'uploaded_at']);

        $folderIds = $resources->pluck('drive_folder_id')->filter()->unique();
        $folders = AdminDriveFolder::whereIn('drive_folder_id', $folderIds)->get(['drive_folder_id', 'name']);

        return view('student.resources', [
            'resources' => $resources,
            'student' => $student,
            'folders' => $folders,
        ]);
    }

    public function trackDownload(Resource $resource)
    {
        $resource->increment('downloads');

        return response()->json([
            'success' => true,
            'downloads' => $resource->downloads,
        ]);
    }

    public function download(Resource $resource)
    {
        if (! $resource->drive_file_id) {
            return response()->json(['success' => false, 'message' => 'File not found.'], 404);
        }

        $fileId = $resource->drive_file_id;
        $url = "https://drive.google.com/uc?export=download&id={$fileId}";

        return redirect($url);
    }
}
