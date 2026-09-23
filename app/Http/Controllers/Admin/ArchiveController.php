<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ArchivedDocTag;
use App\Models\ArchivedDocument;
use App\Support\ProjectTags;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class ArchiveController extends Controller
{
    public function index()
    {
        $archives = ArchivedDocument::with('tags')
            ->published()
            ->orderByDesc('academic_year')
            ->get();
        $tags = ArchivedDocTag::orderBy('tag_name')->get();

        // Manuscripts teams handed in themselves. They are not in the archive
        // until somebody decides - an archived capstone is what similarity
        // checks compare against, so a team cannot put its own PDF in there.
        $pending = ArchivedDocument::pending()
            ->with(['team', 'submitter'])
            ->orderBy('submitted_at')
            ->get();

        return view('capsa.archive', [
            'archives' => $archives,
            'tags' => $tags,
            'pending' => $pending,
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
            'tag_ids' => 'nullable|array|max:'.ProjectTags::MAX,
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
            'tag_ids' => 'nullable|array|max:'.ProjectTags::MAX,
            'tag_ids.*' => 'exists:archived_doc_tags,tag_id',
        ]);

        $archive->update($data);

        if (isset($data['tag_ids'])) {
            $archive->tags()->sync($data['tag_ids']);
        }

        return response()->json($archive->load('tags'));
    }

    /**
     * Publishing or declining a manuscript a team submitted.
     *
     * Publishing puts it in the archive; declining takes it back out
     * entirely, file and all, so the team is free to submit a corrected copy
     * rather than being stuck behind a row nobody kept.
     */
    public function decideSubmission(Request $request, ArchivedDocument $archive)
    {
        $data = $request->validate([
            'decision' => ['required', 'in:published,declined'],
        ]);

        if ($archive->submission_status !== ArchivedDocument::PENDING) {
            return response()->json([
                'success' => false,
                'message' => 'This submission has already been decided.',
            ], 409);
        }

        if ($data['decision'] === 'published') {
            $archive->update(['submission_status' => ArchivedDocument::PUBLISHED]);

            // The kind of project the team said it was on its chosen
            // pre-proposal, so Analytics counts it without anyone retyping it.
            if ($archive->tags()->doesntExist() && ($tags = $archive->team?->selectedPreProposal?->tags)) {
                $archive->tags()->sync(ArchivedDocTag::idsFor($tags));
            }

            return response()->json([
                'success' => true,
                'message' => $archive->title.' is now in the archive.',
            ]);
        }

        $this->deleteStoredFile($archive);
        $archive->tags()->detach();
        $archive->delete();

        return response()->json([
            'success' => true,
            'message' => 'Submission declined. The team can hand in a new copy.',
        ]);
    }

    /** Just the tags, from the tag editor on a capstone's card. */
    public function updateTags(Request $request, ArchivedDocument $archive)
    {
        $data = $request->validate([
            'tag_ids' => ['present', 'array', 'max:'.ProjectTags::MAX],
            'tag_ids.*' => ['integer', 'exists:archived_doc_tags,tag_id'],
        ], [
            'tag_ids.max' => 'Pick at most '.ProjectTags::MAX.' tags.',
        ]);

        $archive->tags()->sync($data['tag_ids']);

        return response()->json(['success' => true, 'tags' => $archive->tags()->pluck('tag_name')]);
    }

    public function destroy(ArchivedDocument $archive)
    {
        $this->deleteStoredFile($archive);
        $archive->tags()->detach();
        $archive->delete();

        return response()->json(['success' => true]);
    }

    /**
     * Remove the PDF behind an archive row.
     *
     * Without this the file stayed on disk after the row was gone - invisible
     * to the app and still occupying storage, which the student submissions
     * made worse because those files are megabytes apiece.
     */
    private function deleteStoredFile(ArchivedDocument $archive): void
    {
        if (! $archive->file_path) {
            return;
        }

        // Staff uploads recorded a Drive-style path or an absolute Windows
        // one rather than a disk-relative path; Storage::delete on either
        // would be a no-op or worse, so only relative paths are touched. A
        // colon is what marks both - no relative path has one.
        if (str_contains($archive->file_path, ':')) {
            return;
        }

        Storage::disk('public')->delete($archive->file_path);
    }
}
