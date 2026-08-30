<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AdminDriveFolder;
use App\Models\Resource;
use App\Services\GoogleDriveService;
use Illuminate\Support\Facades\Auth;

class ResourcesController extends Controller
{
    public function index(GoogleDriveService $drive)
    {
        return view('capsa.resources', [
            'resources' => Resource::orderByDesc('uploaded_at')->get(),
            'folders' => AdminDriveFolder::orderBy('name')->get(),
            'driveConnected' => $drive->isConnected(Auth::guard('expert')->id()),
        ]);
    }
}
