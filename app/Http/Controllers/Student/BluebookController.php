<?php

namespace App\Http\Controllers\Student;

use App\Http\Controllers\Controller;
use App\Models\CapstoneDocument;
use App\Models\Deliverable;
use App\Models\Team;
use App\Support\Bluebook;
use App\Support\StudentTeam;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\Response;

/** The team hands in its Bluebook requirements here: a file, or a Drive link for a video. */
class BluebookController extends Controller
{
    public function index(): View
    {
        $team = $this->team();

        return view('student.bluebook', ['team' => $team, 'bluebook' => $team ? Bluebook::of($team) : null]);
    }

    public function store(Request $request, Deliverable $deliverable): RedirectResponse
    {
        $team = $this->team() ?? abort(403);
        $bluebook = Bluebook::of($team) ?? abort(404);
        $line = $bluebook->lines->first(fn ($row) => (int) $row->item->id === (int) $deliverable->id) ?? abort(404);
        abort_if($bluebook->locked || $line->locked || Bluebook::done($line), 403);

        // The file type the CaPSA/TSA picked for this line: a link only, one
        // kind of file only, or anything. None picked keeps PDF, DOCX or a link.
        $type = $deliverable->fileType();
        $endings = $deliverable->accepts(['pdf', 'docx']);
        $takesFile = $type !== 'link';
        $takesLink = in_array($type, [null, 'link', 'any'], true);

        $request->validate([
            'file' => $takesFile
                ? [$takesLink ? 'required_without:link' : 'required', 'nullable', 'file', ...($endings === ['*'] ? [] : ['mimes:'.implode(',', $endings)]), 'max:25600']
                : ['prohibited'],
            'link' => $takesLink ? [$takesFile ? 'required_without:file' : 'required', 'nullable', 'url', 'max:500'] : ['prohibited'],
        ], [
            'required_without' => 'Choose a file or paste a Drive link.',
            'file.required' => 'Choose a file.',
            'link.required' => 'Paste a Drive link.',
            'mimes' => 'This requirement takes '.$deliverable->fileWords().' only.',
            'prohibited' => 'This requirement takes '.$deliverable->fileWords().' only.',
        ]);

        if ($request->hasFile('file')) {
            $file = $request->file('file');
            $name = $file->getClientOriginalName();
            $ending = strtolower($file->getClientOriginalExtension());
            // A picked type keeps the file's own ending; a guessed one would save a DOCX as a zip.
            $path = $type && preg_match('/^[a-z0-9]{1,8}$/', $ending)
                ? $file->storeAs('bluebook', pathinfo($file->hashName(), PATHINFO_FILENAME).'.'.$ending, 'local')
                : $file->store('bluebook', 'local');
        } else {
            $id = Bluebook::driveId($request->input('link'))
                ?? throw ValidationException::withMessages(['link' => 'Paste a Google Drive link to the video.']);
            $name = $deliverable->title;
            $path = "https://drive.google.com/file/d/{$id}/view";
        }

        CapstoneDocument::create([
            'student_id' => auth()->id(),
            'team_id' => $team->team_id,
            'deliverable_id' => $deliverable->id,
            'file_name' => $name,
            'file_path' => $path,
            'file_version' => (CapstoneDocument::ofTeam($team->team_id)->where('deliverable_id', $deliverable->id)->max('file_version') ?? 0) + 1,
            'document_status' => 'Pending',
        ]);

        return back()->with('success', $deliverable->title.' sent to the Dean\'s Office.');
    }

    public function file(CapstoneDocument $document): Response
    {
        abort_unless($document->team_id && (int) $document->team_id === (int) $this->team()?->team_id, 403);

        return Bluebook::fileResponse($document);
    }

    private function team(): ?Team
    {
        $id = StudentTeam::idFor(auth()->id());

        return $id ? Team::find($id) : null;
    }
}
