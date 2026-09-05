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
        $viewer = Auth::guard('expert')->user();

        // A CaPSA sees what they uploaded, and nobody else's. This page
        // listed every resource in the system regardless of who put it there.
        // The admin runs the system and is not scoped.
        $ownerId = $viewer && ! $viewer->is_admin ? $viewer->expert_id : null;

        return view('capsa.resources', [
            'resources' => Resource::when($ownerId !== null, fn ($q) => $q->where('uploaded_by', $ownerId))
                ->orderByDesc('uploaded_at')
                ->get(),
            'folders' => AdminDriveFolder::orderBy('name')->get(),
            'driveConnected' => $drive->isConnected(Auth::guard('expert')->id()),
            // The sections this CaPSA handles, so the form can offer those
            // rather than every program in the school.
            'mySections' => $viewer?->sections ?? [],
            'isAdmin' => (bool) $viewer?->is_admin,
        ]);
    }
}
