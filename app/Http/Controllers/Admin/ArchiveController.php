<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ArchivedDocTag;
use App\Models\ArchivedDocument;
use Illuminate\Http\Request;

class ArchiveController extends Controller
{
    public function index()
    {
        $archives = ArchivedDocument::with('tags')->orderByDesc('academic_year')->get();
        $tags = ArchivedDocTag::orderBy('tag_name')->get();

        return view('capsa.archive', [
            'archives' => $archives,
            'tags' => $tags,
        ]);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'title' => 'required|string|max:255',
            'abstract' => 'nullable|string',
            'authors' => 'nullable|string|max:500',
            'academic_year' => 'nullable|string|max:20',
            'program' => 'nullable|string|max:50',
            'file_name' => 'nullable|string|max:255',
            'file_path' => 'nullable|string|max:500',
            'file_size' => 'nullable|integer',
            'tag_ids' => 'nullable|array',
            'tag_ids.*' => 'exists:archived_doc_tags,tag_id',
        ]);

        $archive = ArchivedDocument::create($data);

        if (! empty($data['tag_ids'])) {
            $archive->tags()->sync($data['tag_ids']);
        }

        return response()->json($archive->load('tags'), 201);
    }

    public function update(Request $request, ArchivedDocument $archive)
    {
        $data = $request->validate([
            'title' => 'required|string|max:255',
            'abstract' => 'nullable|string',
            'authors' => 'nullable|string|max:500',
            'academic_year' => 'nullable|string|max:20',
            'program' => 'nullable|string|max:50',
            'file_name' => 'nullable|string|max:255',
            'file_path' => 'nullable|string|max:500',
            'file_size' => 'nullable|integer',
            'tag_ids' => 'nullable|array',
            'tag_ids.*' => 'exists:archived_doc_tags,tag_id',
        ]);

        $archive->update($data);

        if (isset($data['tag_ids'])) {
            $archive->tags()->sync($data['tag_ids']);
        }

        return response()->json($archive->load('tags'));
    }

    public function destroy(ArchivedDocument $archive)
    {
        $archive->tags()->detach();
        $archive->delete();

        return response()->json(['success' => true]);
    }
}
