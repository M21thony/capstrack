<?php

namespace App\Models;

use App\Support\StudentTeam;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Storage;

class CapstoneDocument extends Model
{
    protected $table = 'capstone_documents';

    protected $primaryKey = 'file_id';

    protected $fillable = [
        'student_id',
        'team_id',
        'file_name',
        'file_path',
        'file_version',
        'document_status',
        'chapter',
        'deliverable_id',
        'diagram_files',
    ];

    /** @return array<string, string> */
    protected function casts(): array
    {
        // The .drawio and .vsdx files sent with it: [{name, path}], or null for none.
        return ['diagram_files' => 'array'];
    }

    /**
     * The draw.io and Visio files sent with it, for the viewer to draw. A
     * Visio file is a zip, so it goes as base64 for the browser to convert.
     *
     * @return list<array{name: string, xml?: string, vsdx?: string}>
     */
    public function diagrams(): array
    {
        return collect($this->diagram_files ?? [])
            ->filter(fn (array $file) => Storage::disk('local')->exists($file['path']))
            ->map(fn (array $file) => str_ends_with(strtolower($file['name']), '.vsdx')
                ? ['name' => $file['name'], 'vsdx' => base64_encode(Storage::disk('local')->get($file['path']))]
                : ['name' => $file['name'], 'xml' => Storage::disk('local')->get($file['path'])])
            ->values()->all();
    }

    /** Sent as a draw.io or Visio file itself rather than a PDF, so it is read as its diagram pages only. */
    public function isDiagram(): bool
    {
        return $this->file_path !== null && in_array($this->file_path, array_column($this->diagram_files ?? [], 'path'), true);
    }

    /** A file is sent for the uploader's team at the time, and stays that team's if they move. */
    protected static function booted(): void
    {
        static::creating(function (CapstoneDocument $document) {
            $document->team_id ??= StudentTeam::idFor($document->student_id);
        });
    }

    /**
     * What this team has uploaded. A file from before uploads recorded their
     * team counts when its uploader is a member now and it went up after the
     * team was made - one sent earlier was for the team they came from.
     */
    public function scopeOfTeam(Builder $query, int $teamId): Builder
    {
        return $query->where(fn (Builder $q) => $q->where('capstone_documents.team_id', $teamId)
            ->orWhere(fn (Builder $q) => $q->whereNull('capstone_documents.team_id')
                ->whereIn('capstone_documents.student_id', StudentSchoolYear::where('team_id', $teamId)->select('student_id'))
                ->where('capstone_documents.created_at', '>=', Team::where('team_id', $teamId)->select('created_at'))));
    }

    /**
     * The team's papers for its experts: everything but a signed paper, which
     * is handed in on Progress Tracker and checked there by the CaPSA.
     */
    public function scopeExceptSignedPapers(Builder $query): Builder
    {
        return $query->where(fn (Builder $q) => $q->whereNull('capstone_documents.deliverable_id')
            ->orWhereHas('deliverable', fn (Builder $line) => $line->whereNotIn('kind', Deliverable::CHECKED_BY_CAPSA)));
    }

    /**
     * Which paper this upload is a version of, the way versions are counted:
     * its checklist line, else its chapter, else its name.
     */
    public function paperKey(): string
    {
        return match (true) {
            (bool) $this->deliverable_id => 'line:'.$this->deliverable_id,
            filled($this->chapter) => 'chapter:'.$this->chapter,
            default => 'file:'.$this->file_name,
        };
    }

    /** The uploads of a student's team, or their own while they have none. */
    public static function forTeamOf(int $studentId): Builder
    {
        $teamId = StudentTeam::idFor($studentId);

        return $teamId === null ? self::where('student_id', $studentId) : self::ofTeam($teamId);
    }

    public function reviews()
    {
        return $this->hasMany(CapstoneDocumentReview::class, 'file_id', 'file_id');
    }

    /** The checklist deliverable this file was sent for, if any. */
    public function deliverable()
    {
        return $this->belongsTo(Deliverable::class);
    }

    /**
     * Which round this upload is: the first submission, then the 1st, 2nd...
     * revision. Read from the version, which counts uploads of the same
     * requirement (or the same chapter, for files that name none).
     */
    public function roundLabel(): string
    {
        return self::round(max(1, (int) $this->file_version) - 1);
    }

    /** What the team is asked for if this upload is sent back. */
    public function nextRoundLabel(): string
    {
        return self::round(max(1, (int) $this->file_version));
    }

    /**
     * The team's earlier uploads of the same requirement, newest first - what
     * a reviewer compares this one against to see what changed. Matched the
     * way versions are counted: the checklist line, else the chapter, else
     * the file name.
     *
     * @return Collection<int, self>
     */
    public function earlierVersions(): Collection
    {
        return $this->otherVersions()
            ->where('file_version', '<', (int) $this->file_version)
            ->orderByDesc('file_version')
            ->get();
    }

    /**
     * Whether the team may take this upload back, as in Google Classroom: it
     * is the newest of its line and nobody has started on it - no remark, no
     * verdict, no review begun, no CaPSA check of a signed paper.
     */
    public function canBeUnsubmitted(): bool
    {
        $reviews = $this->reviews;

        return $reviews->every(fn (CapstoneDocumentReview $r) => $r->review_status === CapstoneDocumentReview::AWAITING && ! $r->verdict && ! $r->reviewed_at)
            && ! PdfRemark::whereIn('review_id', $reviews->pluck('review_id'))->exists()
            && ! DeliverableCheck::where('file_id', $this->file_id)->exists()
            && ! $this->otherVersions()->where('file_version', '>=', (int) $this->file_version)->exists();
    }

    /** The team's other uploads of the same line: the checklist line, else the chapter, else the file name. */
    private function otherVersions(): Builder
    {
        $teamId = $this->team_id ?? StudentTeam::idFor($this->student_id);

        return ($teamId ? self::ofTeam($teamId) : self::where('student_id', $this->student_id))
            ->where('file_id', '!=', $this->file_id)
            ->when(
                $this->deliverable_id,
                fn ($q) => $q->where('deliverable_id', $this->deliverable_id),
                fn ($q) => $this->chapter ? $q->where('chapter', $this->chapter) : $q->where('file_name', $this->file_name)
            );
    }

    private static function round(int $revision): string
    {
        if ($revision <= 0) {
            return 'First submission';
        }

        $suffix = in_array($revision % 100, [11, 12, 13], true) ? 'th' : ([1 => 'st', 2 => 'nd', 3 => 'rd'][$revision % 10] ?? 'th');

        return $revision.$suffix.' revision';
    }
}
